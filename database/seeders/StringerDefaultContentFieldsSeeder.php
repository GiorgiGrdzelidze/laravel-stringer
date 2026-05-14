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
                'max_length' => 120,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'A 60-80 character SEO-friendly title. No clickbait, no marketing language.',
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
                'max_length' => 400,
                'max_words' => null,
                'min' => null,
                'max' => null,
                'instruction' => 'One or two sentences of plain prose summarizing the post.',
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
                'max_words' => 800,
                'min' => null,
                'max' => null,
                'instruction' => 'Full article body in markdown. Headings, code blocks, lists allowed. Stay in voice for the whole body.',
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
                'instruction' => 'Suggested topic tags. Lowercase, no spaces, hyphenate compounds.',
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
                'instruction' => 'Pick exactly one category slug from the context.categories list, or null when nothing fits.',
                'is_required' => false,
                'sort_order' => 50,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
