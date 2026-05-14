<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | LLM driver
    |--------------------------------------------------------------------------
    | The default driver and per-driver API keys + model identifiers. Driver
    | swaps require nothing more than changing STRINGER_LLM_DRIVER and the
    | matching API key. Models are pinned via env to make upgrades visible
    | in commits rather than buried in package upgrades.
    */
    'llm' => [
        'driver' => env('STRINGER_LLM_DRIVER', 'gemini'),
        'api_keys' => [
            'gemini' => env('STRINGER_GEMINI_API_KEY'),
            'claude' => env('STRINGER_CLAUDE_API_KEY'),
            'openai' => env('STRINGER_OPENAI_API_KEY'),
            'groq' => env('STRINGER_GROQ_API_KEY'),
        ],
        'models' => [
            'gemini' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'claude' => env('CLAUDE_MODEL', 'claude-sonnet-4-5'),
            'openai' => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'groq' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ],
        // HTTP timeout (seconds) for a single LLM round-trip. Dense prompts
        // with multi-locale output and 2k+ word bodies routinely exceed
        // Guzzle's 30s default — bump if you see cURL error 28.
        'http_timeout' => (int) env('STRINGER_LLM_HTTP_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Locales
    |--------------------------------------------------------------------------
    | Drafts are produced in the primary locale, then translated into the
    | rest of the list. Host overrides this to match its own translatable
    | content surface.
    */
    'locales' => [
        'list' => ['en', 'ka', 'ru'],
        'primary' => 'en',
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram
    |--------------------------------------------------------------------------
    | Webhook secret authenticates the incoming POST; allowed_chat_ids is a
    | second-layer allowlist so a leaked webhook URL still cannot drive the
    | bot. Set 'enabled' = false to skip route registration entirely.
    */
    'telegram' => [
        'enabled' => env('STRINGER_TELEGRAM_ENABLED', true),
        'bot_token' => env('STRINGER_TELEGRAM_BOT_TOKEN'),
        'secret' => env('STRINGER_TELEGRAM_WEBHOOK_SECRET'),
        'allowed_chat_ids' => array_values(array_filter(array_map(
            'intval',
            explode(',', (string) env('STRINGER_TELEGRAM_ALLOWED_CHAT_IDS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Table names
    |--------------------------------------------------------------------------
    | Override if the host already has tables with these names for a different
    | purpose, or names its articles table something other than `articles`.
    | `articles` is consulted to add the FK on `blog_topics.article_id` at
    | migration time; if the host table doesn't exist yet the FK is skipped.
    */
    'tables' => [
        'blog_topics' => 'blog_topics',
        'stringer_prompts' => 'stringer_prompts',
        'stringer_content_fields' => 'stringer_content_fields',
        'stringer_settings' => 'stringer_settings',
        'articles' => env('STRINGER_ARTICLES_TABLE', 'articles'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Host model classes
    |--------------------------------------------------------------------------
    | `models.article` names the host's Eloquent class so `BlogTopic::article()`
    | can resolve the relationship. Required only if the host needs to use the
    | relationship — the migration runs without it.
    */
    'models' => [
        'article' => env('STRINGER_ARTICLE_MODEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice
    |--------------------------------------------------------------------------
    | Baked-in fallback used by DefaultPromptBuilder when ManageStringerSettings
    | (Filament) hasn't been touched. Hosts override this to match their own
    | tone (e.g. technical-dry for grdzelo, traveler-evocative for
    | explore-georgia).
    */
    'voice' => [
        'default' => 'clear, accurate, no marketing fluff',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default seeding
    |--------------------------------------------------------------------------
    | Auto-runs the StringerDefaultPrompts / StringerDefaultContentFields
    | seeders on boot if the relevant tables are present and empty. Each
    | seeder is run-once-guarded, so this is idempotent. Tests typically
    | disable this so they can assert on an empty schema.
    */
    'seed_defaults_on_boot' => env('STRINGER_SEED_DEFAULTS_ON_BOOT', true),

    /*
    |--------------------------------------------------------------------------
    | Schedule
    |--------------------------------------------------------------------------
    */
    'schedule' => [
        'auto_generate_cron' => env('STRINGER_AUTO_GENERATE_CRON', '0 9 * * 1'),
        'auto_generate_timezone' => env('STRINGER_AUTO_GENERATE_TZ', 'Asia/Tbilisi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'body_word_cap' => 800,
        'tag_count' => 5,
    ],
];
