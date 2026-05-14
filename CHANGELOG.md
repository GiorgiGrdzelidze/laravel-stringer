# Changelog

All notable changes to `giorgigrdzelidze/laravel-stringer` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — 2026-05-XX

Initial public release.

### Core
- `BlogTopic` model + queue lifecycle (`Queued` → `Drafting` → `Drafted` | `Rejected` | `Failed`), with `auto_publish` + `target_status` per-topic opt-in for publishing.
- `Services\TopicQueue` as the **only** sanctioned mutator of `BlogTopic.status` — single chokepoint for activitylog / metrics hooks.
- `Services\DraftGenerator` orchestrates one topic → one draft in a single `DB::transaction`. Reads `StringerContentField::active()->ordered()` at call time so the field schema is dynamic. Per-field translation for `TranslatableString` / `TranslatableMarkdown` types.

### LLM
- `Contracts\LlmClient` driver-agnostic interface (`draft` + `translate`). `Llm\LlmManager` resolves the configured driver per call.
- Four drivers, all behind `Http::fake()`-coverable wrappers: **Gemini** (default, full implementation), **Claude**, **OpenAI**, **Groq**.
- `Contracts\PromptBuilder` is the **sole producer** of strings sent to the LLM. `Prompts\DbPromptBuilder` reads operator-editable `StringerPrompt` rows with `{{placeholder}}` substitution; `Prompts\DefaultPromptBuilder` is the baked-in fallback when no row matches.

### Telegram
- `Http/Controllers/TelegramWebhookController` + `Http/Middleware/VerifyTelegramSecret` — path-secret auth, chat-id allowlist, 404 on secret mismatch, 200-empty on non-allowlisted chats so Telegram doesn't retry.
- Five commands: `/help`, `/list`, `/file` (aliased as `/draft`), `/spike`, free-text fallback. Bot replies in Georgian.

### Jobs
- `Jobs\GenerateDraftJob` — queued wrapper, `tries=2`, 30-second backoff, idempotency guard against parallel retries.
- `Jobs\AutoGenerateWeeklyJob` + scheduler binding — picks the oldest `Queued` topic or synthesizes an `Auto`-source topic from the host's projects / repositories context.

### Filament
- `BlogTopicResource`, `StringerPromptResource`, `StringerContentFieldResource` and the `ManageStringerSettings` single-form page, all under the **Stringer** navigation group.
- Registered via `Stringer\Laravel\Filament\StringerPlugin` — hosts opt in with `->plugin(StringerPlugin::make())` in their panel provider.

### Schema
- Five migrations: `blog_topics`, `stringer_prompts`, `stringer_content_fields`, additive G1 columns on `blog_topics`, `stringer_settings`.
- Two seeders for neutral defaults (two prompt templates + five content fields covering all seven `FieldType` values).

### Quality
- PHPStan level 6 + Pint (Laravel preset, strict types/comparison/single quotes).
- Pest 4 + Orchestra Testbench, 119 tests / 292 assertions, CI matrix across PHP 8.3 / 8.4 × Laravel 12 / 13.
- MIT license.
