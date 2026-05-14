<?php

declare(strict_types=1);

namespace Stringer\Laravel\Database\Seeders;

use Illuminate\Database\Seeder;
use Stringer\Laravel\Models\StringerPrompt;

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
        return <<<'TEMPLATE'
You are drafting a blog post for human review.

Voice: {{voice}}

Source: {{source}}
Topic hint: {{hint}}

Reference context — recently published content on the site:
{{context}}

Available categories — choose ONE slug or leave null:
{{categories}}

Field schema — produce a single JSON object keyed by field name. Each
field's value type is described below; honor the constraints exactly.
{{field_schema}}

Output the JSON only — no code fences, no preamble, no trailing prose.
TEMPLATE;
    }

    private static function translateTemplate(): string
    {
        return <<<'TEMPLATE'
Translate the following text from English to {{target_locale}}. Preserve
markdown structure, code blocks, JSON keys, HTML tags, and proper nouns.
Output only the translation; no preamble, no quoting.

{{english_text}}
TEMPLATE;
    }
}
