<?php

declare(strict_types=1);

namespace Stringer\Laravel\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\Contracts\ContextBuilder;
use Stringer\Laravel\Contracts\LlmClient;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\DataObjects\LocalizedDraft;
use Stringer\Laravel\Llm\LlmManager;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;

/**
 * One topic → one draft, wrapped in a single DB transaction.
 *
 * Reads the active `StringerContentField` rows at call time so the field
 * schema is dynamic. The LLM is asked for a single JSON object keyed by
 * field name; translatable fields are translated per non-primary locale
 * via a second LLM call each. On any failure inside the transaction the
 * host's `ContentTarget::write()` is never reached and the topic stays
 * untouched — `GenerateDraftJob` (Phase 7) marks it Failed externally.
 *
 * The status transition into `Drafting` is intentionally owned by
 * `GenerateDraftJob` (Phase 7), not by this class — the generator is
 * synchronous and the job is the unit that fences against parallel
 * retries.
 */
final class DraftGenerator
{
    public function __construct(
        private readonly LlmManager $llmManager,
        private readonly ContextBuilder $contextBuilder,
        private readonly PromptBuilder $promptBuilder,
        private readonly ContentTarget $target,
        private readonly TopicQueue $topicQueue,
        private readonly ConfigRepository $config,
    ) {}

    public function generate(BlogTopic $topic): Model
    {
        /** @var list<StringerContentField> $fields */
        $fields = StringerContentField::query()->active()->ordered()->get()->all();
        $context = $this->contextBuilder->build();
        $llm = $this->llmManager->make();

        // LLM round-trips are slow and don't write to the DB — keep them
        // outside the transaction so a multi-locale draft doesn't hold a
        // DB connection open for 30+ seconds. If any LLM call throws, no
        // transaction has been opened yet, the topic stays in its current
        // state, and the queue worker's retry / failed() path handles it.
        $prompt = $this->promptBuilder->buildDraftPrompt($topic, $context, $fields);
        $rawDraft = $llm->draft($prompt, $context);
        $primary = $this->parseLlmJson($rawDraft, $fields);

        $primaryLocale = (string) $this->config->get('stringer.locales.primary', 'en');
        /** @var list<string> $locales */
        $locales = (array) $this->config->get('stringer.locales.list', [$primaryLocale]);

        $finalFields = $this->localizeFields($primary, $fields, $primaryLocale, $locales, $llm);

        // The transaction wraps only the two writes that have to succeed
        // together: the host's content target + the topic's status flip
        // to Drafted (which records article_id + generated_by).
        return DB::transaction(function () use ($topic, $finalFields): Model {
            $result = $this->target->write(new LocalizedDraft($finalFields), $topic);

            $this->topicQueue->markDrafted($topic, $result, $this->llmManager->modelName());

            return $result;
        });
    }

    /**
     * @param  list<StringerContentField>  $fields
     * @return array<string, mixed> Primary-locale values, one entry per active field.
     */
    private function parseLlmJson(string $raw, array $fields): array
    {
        $cleaned = trim($raw);
        // Strip occasional code-fence wrapping.
        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleaned) ?? $cleaned;

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($cleaned, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('LLM response is not valid JSON: '.$e->getMessage(), previous: $e);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('LLM response did not decode to a JSON object.');
        }

        $result = [];
        foreach ($fields as $field) {
            if (! array_key_exists($field->name, $decoded)) {
                if ($field->is_required) {
                    throw new RuntimeException("LLM response is missing required field '{$field->name}'.");
                }

                continue;
            }

            $result[$field->name] = $decoded[$field->name];
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $primary
     * @param  list<StringerContentField>  $fields
     * @param  list<string>  $locales
     * @return array<string, mixed>
     */
    private function localizeFields(
        array $primary,
        array $fields,
        string $primaryLocale,
        array $locales,
        LlmClient $llm,
    ): array {
        $out = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field->name, $primary)) {
                continue;
            }

            if (! $field->type->isTranslatable()) {
                $out[$field->name] = $primary[$field->name];

                continue;
            }

            $value = $primary[$field->name];
            if (! is_string($value)) {
                throw new RuntimeException("Translatable field '{$field->name}' is not a string in the LLM response.");
            }

            $perLocale = [$primaryLocale => $value];

            foreach ($locales as $locale) {
                if ($locale === $primaryLocale) {
                    continue;
                }

                $prompt = $this->promptBuilder->buildTranslationPrompt($value, $locale);
                $perLocale[$locale] = $llm->translate($prompt, $primaryLocale, $locale);
            }

            $out[$field->name] = $perLocale;
        }

        return $out;
    }
}
