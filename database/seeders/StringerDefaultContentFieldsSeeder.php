<?php

declare(strict_types=1);

namespace Stringer\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Models\StringerContentField;

/**
 * Seeds the five baseline content fields a fresh install needs to produce
 * a usable draft: title, excerpt, body, tags, category.
 *
 * Run-once guard so the seeder is safe to call on every boot.
 */
final class StringerDefaultContentFieldsSeeder extends Seeder
{
    public function run(): void
    {
        if (StringerContentField::query()->exists()) {
            return;
        }

        $now = now();
        $translatable = json_encode(['en', 'ka', 'ru']);

        StringerContentField::query()->insert([
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'created_at' => $now,
                'updated_at' => $now,
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
                'sort_order' => 40,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
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
                'sort_order' => 50,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
