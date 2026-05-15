# Screenshots

PNGs in this directory are wired into the main README. Recommended capture: ~1600px wide, light theme, with realistic data (not lorem-ipsum). PNG/WEBP preferred.

## Currently in the README

| Filename | What it shows |
|----------|---------------|
| `topics-listing.png` | `/admin/blog-topic-resource/blog-topics` — list view with multiple topics in different states |
| `telegram-flow-1.png` | A topic being queued via Telegram (menu tap or `/generate`), with the bot's ack message |
| `telegram-flow-2.png` | Draft-ready photo card: cover image + bold title + excerpt + stats line + admin link |
| `telegram-flow-3.png` | Failure notification: cover-driver quota / paid-plan / JSON parse error surfaced to chat |

## Worth adding (would strengthen the README)

| Filename | What to capture |
|----------|-----------------|
| `article-media-tab.png` | `/admin/articles/N/edit → Media tab` with the cover attached and the OG / Twitter upload slots visible. Proves the image pipeline + multi-channel overrides. |
| `article-seo-tab.png` | `/admin/articles/N/edit → SEO tab` with `meta_title`, `meta_description`, `og_title`, `og_description`, `twitter_title`, `twitter_description` populated across `EN / KA / RU`. Proves multi-channel SEO. |
| `settings-page.png` | `/admin/manage-stringer-settings` showing the form (Voice card, Body word cap, cron, allowed chat IDs). Proves no-deploy operator control. |
| `prompts-list.png` *(optional)* | `/admin/stringer-prompt-resource/stringer-prompts` listing the 3 seeded rows (`draft`, `translate`, `cover_image`). Proves the DB-editable template surface. |
| `content-fields-list.png` *(optional)* | `/admin/stringer-content-field-resource/stringer-content-fields` showing the 12 baseline fields. Proves the field schema is data-driven. |
