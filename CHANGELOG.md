# Changelog

All notable changes to `giorgigrdzelidze/laravel-stringer` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] — 2026-05-15

Reply-keyboard menu, post-LLM sanitisation, and a much sharper default prompt.

### Telegram menu (new)
- Drill-down reply-keyboard menu reached via `/start` and `/menu`. Tree: Root → Generate / Topics / Categories / Settings / Help, with back buttons everywhere and per-chat state persistence.
- Universal core in `src/Telegram/Menu/*` (router, renderer, translator, state store, pending-input store, reusable `LanguagePickerNode`) — host-specific tree lives in `src/Telegram/Menu/Stringer/*`.
- Auto-deletes the user's button tap **and** the bot's previous menu screen so the chat shows a single live message at any time.
- Root-button fallback handles the rapid-tap race condition that would otherwise drop button labels into the legacy free-text dispatcher.
- Per-chat language preference (`en`/`ka`/`ru`) persisted in `telegram_chat_states`. Default locale read from `app.locale`.
- "Type your hint" pending-input flow with 60-second TTL in `telegram_pending_inputs`.
- New `Contracts\CategoryDirectory` for host-supplied category lists. `NullCategoryDirectory` ships as the default — hosts override the binding to wire their own.
- New artisan command `stringer:telegram:register-commands` populates the bot's blue menu button with per-locale command descriptions via `setMyCommands`.

### Generator and prompts
- `Services\AiTellSanitizer` strips ~30 giveaway phrases ("Furthermore,", "In conclusion,", "In today's fast-paced world,"…) from translatable string/markdown values. Code blocks and inline code spans are preserved verbatim.
- `DraftGenerator` accepts per-locale objects from LLMs that return multilingual output in one call — skips the redundant translation passes when the model already produced all locales.
- `TagList` fields now accept either a flat array (single-locale) or a per-locale shape `{en:[…], ka:[…], ru:[…]}` with parallel index ordering. Host adapters create multilingual Spatie tags.
- `DefaultPromptBuilder` rewritten as a long-form senior-staff-engineer essay prompt: required body structure (opening → problem → mechanism → implementation → tradeoffs → edge cases → anti-pattern → close), 2,500-word body cap, one full worked few-shot example.
- Default field constraints tightened: `title` 70 chars (was 120), `excerpt` 200 chars (was 400), `body` 2,500 words (was 800), `tags` defaults to multilingual.

### LLM drivers
- Configurable HTTP timeout via `STRINGER_LLM_HTTP_TIMEOUT` (default 120s). Threaded through `LlmManager` → `AbstractLlmClient` → all four drivers — fixes cURL error 28 on dense prompts.

### Telegram bot
- `/file` renamed to `/generate` (`/draft` still aliased).
- All command replies and the help text ported to English (was Georgian).
- Rich draft-ready notification: bolded title, word-count / locale-count / tag-count stats, clickable `Open in admin` HTML anchor.
- Telegram supergroup chat ids are now stored as signed `BIGINT` (was `UNSIGNED BIGINT` — supergroups have negative ids).

### Filament
- `ManageStringerSettings` form polished: real Filament `Section` chrome, primary `Save` action with `Cmd/Ctrl+S` keybinding, spacing matched to other resources.

### Documentation
- README rewritten: quickstart, Mermaid architecture diagram, screenshots gallery, sample generated draft, FAQ, troubleshooting, roadmap.
- `docs/screenshots/README.md` guides what PNGs to drop where for the README embeds.

### Schema
- New migrations: `telegram_chat_states`, `telegram_pending_inputs`, `last_menu_message_id` column on chat-state, signed-bigint `requested_by_chat_id` on `blog_topics`.

### Quality
- 125 tests / 317 assertions. PHPStan level 6 clean. Pint clean.

## [0.1.0] — 2026-05-14

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
