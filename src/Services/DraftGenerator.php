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
 * One topic → one draft.
 *
 * Two phases:
 *  1. **LLM phase (outside any DB transaction).** Reads active
 *     `StringerContentField` rows at call time so the field schema is
 *     dynamic. Asks the LLM for a single JSON object keyed by field
 *     name; for each translatable field, makes one translation call per
 *     non-primary locale. Slow HTTP — kept out of the transaction so it
 *     doesn't hold a DB connection open for 30+ seconds.
 *  2. **Write phase (single DB transaction).** `ContentTarget::write` +
 *     `TopicQueue::markDrafted` happen together inside `DB::transaction`,
 *     so a failure in either rolls both back atomically.
 *
 * A throw in the LLM phase propagates up before the transaction even
 * opens — the topic stays in its current state and `GenerateDraftJob`'s
 * retry / failed() path handles it.
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
        private readonly AiTellSanitizer $sanitizer,
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
        $cleaned = self::stripWrappers($raw);

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($cleaned, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $firstError) {
            // LLMs ship malformed JSON in several recurring shapes. Run the
            // cleanup pipeline and try once more. Each step is idempotent
            // and safe-by-default; if all fixes still don't yield a valid
            // document, surface the original error so debugging stays honest.
            try {
                $repaired = self::repairLlmJson($cleaned);
                /** @var mixed $decoded */
                $decoded = json_decode($repaired, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new RuntimeException('LLM response is not valid JSON: '.$firstError->getMessage(), previous: $firstError);
            }
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

            // LLMs occasionally return all locales in one shot, keyed by
            // locale code. Honor that shape directly and skip the per-locale
            // translation calls — saves N-1 round-trips per translatable
            // field. Backfill any missing locales with the primary value.
            if (is_array($value)) {
                $perLocale = [];
                $primaryValue = null;
                foreach ($locales as $locale) {
                    $localized = $value[$locale] ?? null;
                    if (is_string($localized) && $localized !== '') {
                        $perLocale[$locale] = $this->sanitizer->sanitize($localized);
                        if ($locale === $primaryLocale) {
                            $primaryValue = $perLocale[$locale];
                        }
                    }
                }

                if ($primaryValue === null) {
                    throw new RuntimeException("Translatable field '{$field->name}' is missing the primary locale '{$primaryLocale}'.");
                }

                // Backfill (or repair) non-primary locales by translating from
                // the primary value when they are missing OR when the LLM
                // returned the primary text verbatim (a common "lazy" shape on
                // long fields like body: same English in every locale slot).
                // Either case would otherwise produce "translations" that are
                // still English — visible to the reader and bad for SEO.
                foreach ($locales as $locale) {
                    if ($locale === $primaryLocale) {
                        continue;
                    }

                    $existing = $perLocale[$locale] ?? null;
                    if (is_string($existing) && $existing !== '' && $existing !== $primaryValue) {
                        continue;
                    }

                    $prompt = $this->promptBuilder->buildTranslationPrompt($primaryValue, $locale);
                    $perLocale[$locale] = $this->sanitizer->sanitize(
                        $llm->translate($prompt, $primaryLocale, $locale),
                    );
                }

                $out[$field->name] = $perLocale;

                continue;
            }

            if (! is_string($value)) {
                throw new RuntimeException("Translatable field '{$field->name}' is not a string in the LLM response.");
            }

            $sanitizedPrimary = $this->sanitizer->sanitize($value);
            $perLocale = [$primaryLocale => $sanitizedPrimary];

            foreach ($locales as $locale) {
                if ($locale === $primaryLocale) {
                    continue;
                }

                $prompt = $this->promptBuilder->buildTranslationPrompt($sanitizedPrimary, $locale);
                $perLocale[$locale] = $this->sanitizer->sanitize($llm->translate($prompt, $primaryLocale, $locale));
            }

            $out[$field->name] = $perLocale;
        }

        return $out;
    }

    /**
     * Strip the wrappers LLMs habitually add around JSON output:
     * leading/trailing whitespace, fenced code blocks (```json … ```),
     * and any prose preamble before the first `{` or after the last `}`.
     * Conservative — anything inside the JSON-object span is preserved
     * verbatim; further repair (smart quotes, trailing commas, control
     * chars) happens only if the strict parse fails.
     */
    private static function stripWrappers(string $raw): string
    {
        $cleaned = trim($raw);

        $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleaned) ?? $cleaned;
        $cleaned = trim($cleaned);

        $first = strpos($cleaned, '{');
        $last = strrpos($cleaned, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $cleaned = substr($cleaned, $first, $last - $first + 1);
        }

        return $cleaned;
    }

    /**
     * Apply the standard set of "make this LLM output parseable" repairs:
     * 1. Escape unescaped control characters inside string literals
     *    (Gemini and Claude both drop real \n inside long body fields).
     * 2. Normalize smart / curly quotes to straight quotes.
     * 3. Strip trailing commas before `}` and `]`.
     * Each step is conservative and only touches the regions where the
     * malformation has been observed; the structural punctuation outside
     * string scope is preserved.
     */
    private static function repairLlmJson(string $json): string
    {
        $json = self::escapeControlCharsInStrings($json);
        $json = self::normalizeSmartQuotes($json);
        $json = self::stripTrailingCommas($json);

        return $json;
    }

    /**
     * Replace U+201C / U+201D / U+2018 / U+2019 / U+00AB / U+00BB with
     * straight ASCII quotes. LLM-emitted "smart" punctuation inside JSON
     * string literals reliably breaks PHP's strict decoder.
     */
    private static function normalizeSmartQuotes(string $json): string
    {
        return strtr($json, [
            "\u{201C}" => '"',
            "\u{201D}" => '"',
            "\u{2018}" => "'",
            "\u{2019}" => "'",
            "\u{00AB}" => '"',
            "\u{00BB}" => '"',
            "\u{201E}" => '"',
            "\u{201F}" => '"',
        ]);
    }

    /**
     * Remove trailing commas before `}` or `]` (`{"k":"v",}` → `{"k":"v"}`).
     * Many LLMs treat JSON like JavaScript and emit trailing commas after
     * the last element of an object / array. The regex is safe because
     * any literal comma followed by closing brace inside a string is
     * already escaped or part of a comma+quote pair, not bare punctuation.
     */
    private static function stripTrailingCommas(string $json): string
    {
        return preg_replace('/,\s*([}\]])/u', '$1', $json) ?? $json;
    }

    /**
     * Walk the JSON document and escape raw control characters that appear
     * inside string literals. Tolerates the "LLM dropped a real newline
     * inside a multi-paragraph body field" case without disturbing the
     * structural punctuation (curly braces, commas, colons) that lives
     * outside string scope.
     */
    private static function escapeControlCharsInStrings(string $json): string
    {
        $out = '';
        $inString = false;
        $escapeNext = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];

            if ($escapeNext) {
                $out .= $char;
                $escapeNext = false;

                continue;
            }

            if ($inString && $char === '\\') {
                $out .= $char;
                $escapeNext = true;

                continue;
            }

            if ($char === '"') {
                $inString = ! $inString;
                $out .= $char;

                continue;
            }

            if ($inString) {
                $code = ord($char);
                if ($code < 0x20) {
                    $out .= match ($char) {
                        "\n" => '\\n',
                        "\r" => '\\r',
                        "\t" => '\\t',
                        "\f" => '\\f',
                        "\x08" => '\\b',
                        default => sprintf('\\u%04x', $code),
                    };

                    continue;
                }
            }

            $out .= $char;
        }

        return $out;
    }
}
