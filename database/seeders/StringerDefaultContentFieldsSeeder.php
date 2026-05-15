<?php

declare(strict_types=1);

namespace Stringer\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Models\StringerContentField;

/**
 * Seeds the twelve baseline content fields a fresh install needs to
 * produce a fully-populated draft + per-channel social previews:
 * title, excerpt, slug, body, meta_title, meta_description, og_title,
 * og_description, twitter_title, twitter_description, tags, category.
 *
 * Per-name idempotency: existing rows are left untouched so operator
 * edits survive re-runs. New fields land on existing installs without
 * a wipe.
 */
final class StringerDefaultContentFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $translatable = json_encode(['en', 'ka', 'ru']);

        $rows = [
            [
                'name' => 'title',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 70,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'Concrete, specific, keyword-front-loaded title. 50–70 characters. No clickbait ("You won\'t believe…"), no marketing words ("Ultimate", "Complete", "Definitive", "Mastering"), no questions. Sentence-case for English; native conventions for other locales.',
                'is_required' => true,
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'excerpt',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 200,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'One sentence stating the post\'s specific value to the reader. Active voice, ~25–35 words. Not a teaser, not a question. Should stand alone as a meta description.',
                'is_required' => true,
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'name' => 'slug',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 60,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'URL slug, one per locale. Lowercase, kebab-case, ASCII only, 3–6 words, keyword-focused. Rules per locale: English slug should be a semantic English version of the title (translate non-English titles into English keywords — e.g. "კუს ტბა" → "turtle-lake"). Georgian and Russian slugs are Latin transliterations of that locale\'s title (e.g. ka="kus-tba", ru="cherepashkoye-ozero"). Preserve technology / library / brand names verbatim across all locales ("laravel" stays "laravel", "rsge" stays "rsge"). No stop words (the, a, of, in). No trailing hyphens. Example output: {"en":"turtle-lake","ka":"kus-tba","ru":"cherepashkoye-ozero"}.',
                'is_required' => false,
                'sort_order' => 25,
                'is_active' => true,
            ],
            [
                'name' => 'body',
                'type' => FieldType::TranslatableMarkdown->value,
                'locales' => $translatable,
                'max_length' => null,
                'max_words' => 2500,
                'min' => null,
                'max' => null,
                'instruction' => 'Long-form technical essay in markdown. Aim for 1800-2500 words. Follow the prompt\'s required body structure (opening → problem → mechanism → implementation → tradeoffs → edge cases → when-not-to-use → closing). Open with a concrete incident with real numbers, never a definition. Use `##` and `###` only. Code samples must compile and reference real APIs. Cite real source-file paths or documentation when explaining mechanism — never invent paths. Discuss at least one alternative approach in fair terms. Cover 2-4 concrete edge cases. Include an explicit anti-pattern section. Close with a tomorrow-morning recommendation, never "In conclusion".',
                'is_required' => true,
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'name' => 'meta_title',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 60,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'SEO meta title for search-engine result pages. 50–60 characters. Front-load the primary keyword, end with a brand tail when room allows (e.g. " — grdzelo.com"). Differ from the article title: the article title can be playful, the meta title must be search-keyword-first. Sentence-case for English; native conventions for other locales.',
                'is_required' => true,
                'sort_order' => 35,
                'is_active' => true,
            ],
            [
                'name' => 'meta_description',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 160,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'SEO meta description for search-engine result pages. 140–160 characters. One or two sentences that promise a concrete payoff to the searcher and include the primary keyword naturally. Active voice. No "Learn how to…" preambles, no questions, no ellipsis. Distinct from the excerpt — write specifically for the SERP click decision.',
                'is_required' => true,
                'sort_order' => 36,
                'is_active' => true,
            ],
            [
                'name' => 'og_title',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 70,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'Open Graph title for Facebook / LinkedIn / Slack link previews. 50–70 characters. Slightly more conversational and curiosity-baiting than meta_title (which is search-keyword-first) — readers tap shared links because of an emotional hook, not a keyword. Sentence case. No emoji.',
                'is_required' => false,
                'sort_order' => 37,
                'is_active' => true,
            ],
            [
                'name' => 'og_description',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 200,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'Open Graph description for Facebook / LinkedIn / Slack link previews. 120–200 characters. Tease the most useful single concrete payoff inside the article. Different shape from meta_description: meta is for SERP scanning, OG is for a friend\'s scrolling thumb on social. Active voice, one specific promise, no clickbait questions.',
                'is_required' => false,
                'sort_order' => 38,
                'is_active' => true,
            ],
            [
                'name' => 'twitter_title',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 70,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'Twitter card title. 50–70 characters. Punchier than og_title — Twitter timelines have less patience. Lead with the specific thing the reader learns. May be the same as og_title when the post is short and tactile; differ deliberately when the topic benefits from a tighter hook.',
                'is_required' => false,
                'sort_order' => 41,
                'is_active' => true,
            ],
            [
                'name' => 'twitter_description',
                'type' => FieldType::TranslatableString->value,
                'locales' => $translatable,
                'max_length' => 200,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'Twitter card description. 100–200 characters. One short, declarative sentence that earns the reader\'s tap. Avoid threading-style "🧵" or "(1/n)" framing. May share substance with og_description but tighten and prune the wording.',
                'is_required' => false,
                'sort_order' => 42,
                'is_active' => true,
            ],
            [
                'name' => 'tags',
                'type' => FieldType::TagList->value,
                'locales' => null,
                'max_length' => null,
                'max_words' => null,
                'min' => 3,
                'max' => 5,
                'instruction' => 'Suggested topic tags. Lowercase, no spaces, hyphenate compounds. Return as a JSON object keyed by locale code, each value an array of 3–5 tags. Translate each tag into all locales; do NOT translate technology / library / brand names ("laravel" stays "laravel"). Keep the same ordering across locales (index 0 in en === index 0 in ka === index 0 in ru). Example: {"en": ["laravel", "queue"], "ka": ["ლარაველი", "რიგი"], "ru": ["ларавель", "очередь"]}.',
                'is_required' => true,
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'name' => 'category',
                'type' => FieldType::Category->value,
                'locales' => null,
                'max_length' => null,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'Pick exactly one category slug from the context.categories list, or null when nothing fits cleanly. Do NOT invent slugs that aren\'t in the list.',
                'is_required' => false,
                'sort_order' => 60,
                'is_active' => true,
            ],
        ];

        foreach ($rows as $row) {
            // Per-name idempotency. Existing rows (possibly hand-edited by
            // the operator) are left untouched; new field names land on
            // existing installs without disturbing prior edits.
            $exists = StringerContentField::query()->where('name', $row['name'])->exists();

            if ($exists) {
                continue;
            }

            StringerContentField::query()->insert([
                ...$row,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
