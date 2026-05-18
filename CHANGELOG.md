# Changelog

All notable changes to `giorgigrdzelidze/laravel-stringer` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.3.0] — 2026-05-15

Cover image pipeline, multi-channel SEO, photo-card notifications, every-error-to-chat, and a hardened JSON parser.

### Cover image pipeline (new)
- Six image drivers behind a single `ImageGenerator` contract: **Imagen 3** (default, reuses `STRINGER_GEMINI_API_KEY`), **Unsplash** (free real-photo path), **DALL-E 3**, **FLUX dev** (fal.ai), **Gemini-image** (free-tier `gemini-2.5-flash-image`), and **Picsum** (no-key placeholder for smoke tests).
- New `GenerateCoverImageJob` chained off `GenerateDraftJob` — fires automatically after a successful draft commit when `STRINGER_IMAGE_ENABLED=true`.
- LLM polish step: the cover job feeds the title + excerpt through the LLM to produce a topic-tailored visual prompt, then hands that prompt to the image driver. Each cover is scene-specific instead of templated.
- New `ContentTarget::attachCover()` host hook — package never imports a media library directly; hosts route bytes to wherever their cover lives (Spatie Media Library, S3, etc.).
- New artisan command `stringer:images:backfill` — dispatches `GenerateCoverImageJob` for every Drafted topic missing a cover, with `--driver=`, `--limit=`, and `--sync` options.
- Per-driver master-size and aspect-ratio mapping (Imagen / FLUX accept arbitrary sizes; DALL-E snaps to its supported set; Unsplash searches landscape orientation).
- `GeneratedImage` DTO carries `bytes`, `mime`, dimensions, `sourceDriver`, optional `attribution` (filled by Unsplash, null for AI), and the final `prompt` — so hosts can store an audit trail in `custom_properties`.
- Hard cap on operator regenerate taps via `STRINGER_IMAGE_MAX_REGENERATES=3` (default).

### Image-prompt template
- `PromptBuilder::buildImagePrompt(title, excerpt, style)` added to the contract.
- `DefaultPromptBuilder::IMAGE_TEMPLATE` is an art-director-style instruction set: scene picking rules with five concrete topic→scene worked examples, mandatory composition (16:9, rule-of-thirds, single focal subject, specific lighting/medium/camera angle/depth), tone calibration matrix (documentary / illustration / lifestyle / archival), hard exclusions (no text, no logos, no faces close-up, no clichés like tangled rope for "complexity" or lightbulbs for "idea").
- DB-editable via the new seeded `cover_image` `StringerPrompt` row — operators tweak the visual prompt template in Filament without a deploy.

### Multi-channel SEO content fields
- Four new seeded translatable fields: `og_title`, `og_description`, `twitter_title`, `twitter_description`. LLM produces distinct copy per channel — meta is search-keyword-first, OG is curiosity-baiting for social, Twitter is punchy hook for timeline.
- `slug` added as a seeded translatable field — LLM produces explicit per-locale slugs (EN translated semantically, KA/RU transliterated to Latin) instead of relying on title-derived auto-fallback.
- Per-name seeder idempotency: `StringerDefaultContentFieldsSeeder` and `StringerDefaultPromptsSeeder` now upsert by `name` / `(key, locale)` instead of all-or-nothing. Existing installs pick up new fields without disturbing operator edits to older rows.
- Default baseline grew from 5 → 12 content fields: `title`, `excerpt`, `slug`, `body`, `meta_title`, `meta_description`, `og_title`, `og_description`, `twitter_title`, `twitter_description`, `tags`, `category`.

### Telegram notifications
- `TelegramClient::sendPhoto` now supports multipart upload from a local file path — Telegram can't fetch `localhost` URLs, so the cover bytes are uploaded directly instead of linked.
- Draft-ready notification is now a rich **photo card**: cover image + host badge (from `config('app.name')`) + bold title + short description (excerpt) + stats line (words · locales · tags) + admin link. Replaces the text-only "Draft is ready" message when images are enabled.
- **Every error surfaces in chat.** Both `GenerateDraftJob::failed()` and `GenerateCoverImageJob` catch-block route through a shared humanizer that pulls the upstream `"message"` field out of Laravel HTTP exceptions and emits a single Telegram message with: host badge, `❌ Draft #N failed` or `⚠️ Cover generation failed via {driver}`, the humanized reason, the exception class, and an admin link.
- Humanizer tolerates Guzzle's body-summary truncation (the `(truncated...)` marker that clips `RequestException` bodies at ~120 chars) — operators see useful prose even on long upstream errors.

### Generator robustness
- **JSON repair pipeline** in `DraftGenerator::parseLlmJson` — when strict decode fails, automatically strip wrappers (code fences, preamble before `{`, trailing prose after `}`), normalize smart quotes (`"" '' « » ‚ „`) to straight ASCII, strip trailing commas (`{"k":"v",}` → `{"k":"v"}`), and escape unescaped control chars inside string literals. Three real Gemini-Flash malformation patterns from production traffic are now covered by regression tests.
- **Retry-guard fix** — `GenerateDraftJob::isRecentParallelRetry` now only fires on the first attempt. Previous behavior silently skipped retries (the topic was already in `Drafting` from attempt 1's `markDrafting`), which meant `failed()` never ran and the operator never got the notification.
- `Gemini` driver now pins `maxOutputTokens=8192` + `temperature=0.7` explicitly — fixes silent truncation on long-body drafts.

### Filament
- `StringerPromptResource` is now **read-only-with-edit**: `canCreate()=false`, duplicate action removed, `CreateStringerPrompt` page deleted. Operators edit the three seeded rows (`draft`, `translate`, `cover_image`) — creating new prompt rows was a footgun (the lookup picks `LIMIT 1`, so a stale duplicate could silently win).
- `ManageStringerSettings` page Save button spacing fixed via inline styles. Discovered while shipping that Tailwind utility classes in package-shipped blade views don't render because the host's Tailwind build doesn't scan `vendor/`.

### Schema
- No new tables. Twelve seeded content fields and three seeded prompt rows (`cover_image` is the new one). All idempotent on existing installs.

### Documentation
- README restructured around real screenshots — five themed groups: Topics queue, Articles dashboard, Telegram draft pipeline (success + error), Telegram menu navigation, and Operator surfaces (SEO tab + Settings + Prompts + Content fields).
- New "Cover images" section covers driver selection, master-size + auto-derived crops, prompt editing, backfill command, and telemetry.

### Quality
- 175 tests / 470 assertions. PHPStan level 6 clean. Pint clean.

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
