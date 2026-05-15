<?php

declare(strict_types=1);

namespace Stringer\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Stringer\Laravel\Models\StringerPrompt;
use Stringer\Laravel\Prompts\DefaultPromptBuilder;

/**
 * Seeds the three neutral baseline prompt templates Stringer needs to
 * start operating: one for drafting, one for translating, and one for
 * cover-image generation.
 *
 * Run-once: no-ops if any `StringerPrompt` row already exists, so booting
 * the host doesn't re-seed on every artisan call.
 */
final class StringerDefaultPromptsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'key' => 'draft',
                'locale' => null,
                'content' => self::draftTemplate(),
                'description' => 'Default draft-generation prompt. Edited via Filament.',
                'variables' => json_encode([
                    'voice', 'source', 'hint', 'context', 'categories', 'field_schema',
                ]),
                'is_active' => true,
            ],
            [
                'key' => 'translate',
                'locale' => null,
                'content' => self::translateTemplate(),
                'description' => 'Default translation prompt. Edited via Filament.',
                'variables' => json_encode(['english_text', 'target_locale']),
                'is_active' => true,
            ],
            [
                'key' => 'cover_image',
                'locale' => null,
                'content' => self::imageTemplate(),
                'description' => 'Default cover-image visual prompt. Edited via Filament.',
                'variables' => json_encode(['title', 'excerpt', 'style']),
                'is_active' => true,
            ],
        ];

        // Per-key idempotency. Lets new prompt keys land on existing installs
        // without disturbing edits the operator has already made to older
        // rows — only insert when this `(key, locale)` pair is missing.
        foreach ($rows as $row) {
            $exists = StringerPrompt::query()
                ->where('key', $row['key'])
                ->where('locale', $row['locale'])
                ->exists();

            if ($exists) {
                continue;
            }

            StringerPrompt::query()->insert([
                ...$row,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private static function draftTemplate(): string
    {
        return DefaultPromptBuilder::DRAFT_TEMPLATE;
    }

    private static function translateTemplate(): string
    {
        return DefaultPromptBuilder::TRANSLATE_TEMPLATE;
    }

    private static function imageTemplate(): string
    {
        return DefaultPromptBuilder::IMAGE_TEMPLATE;
    }
}
