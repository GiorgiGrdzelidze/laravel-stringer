<?php

declare(strict_types=1);

namespace Stringer\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Stringer\Laravel\Models\StringerPrompt;
use Stringer\Laravel\Prompts\DefaultPromptBuilder;

/**
 * Seeds the two neutral baseline prompt templates Stringer needs to start
 * operating: one for drafting, one for translating.
 *
 * Run-once: no-ops if any `StringerPrompt` row already exists, so booting
 * the host doesn't re-seed on every artisan call.
 */
final class StringerDefaultPromptsSeeder extends Seeder
{
    public function run(): void
    {
        if (StringerPrompt::query()->exists()) {
            return;
        }

        StringerPrompt::query()->insert([
            [
                'key' => 'draft',
                'locale' => null,
                'content' => self::draftTemplate(),
                'description' => 'Default draft-generation prompt. Edited via Filament.',
                'variables' => json_encode([
                    'voice', 'source', 'hint', 'context', 'categories', 'field_schema',
                ]),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'translate',
                'locale' => null,
                'content' => self::translateTemplate(),
                'description' => 'Default translation prompt. Edited via Filament.',
                'variables' => json_encode(['english_text', 'target_locale']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private static function draftTemplate(): string
    {
        return DefaultPromptBuilder::DRAFT_TEMPLATE;
    }

    private static function translateTemplate(): string
    {
        return DefaultPromptBuilder::TRANSLATE_TEMPLATE;
    }
}
