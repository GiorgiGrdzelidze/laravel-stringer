# laravel-stringer

Telegram-driven LLM blog drafter for Laravel. Pairs a Telegram bot with an LLM pipeline to produce **per-locale drafts** for human review. Never auto-publishes implicitly — auto-publish is an explicit per-topic opt-in.

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

---

## What it does

`stringer` is a small back office for blog content:

1. The operator (or an automated scheduler) creates a **topic** — a hint, a tag, a stray idea.
2. A queued job composes an LLM prompt from a configurable **field schema** (`StringerContentField`) and a configurable **prompt template** (`StringerPrompt`), both editable in Filament without a deploy.
3. The LLM returns a JSON object keyed by field name; non-translatable fields are kept as-is, `TranslatableString` / `TranslatableMarkdown` fields are translated per locale via a second LLM call each.
4. The package hands a `LocalizedDraft` to the host's `ContentTarget` adapter. The host writes the draft to its content model (`status='draft'`, `publish_at=null`) so a human can review it before it goes live.
5. When the operator trusts the pipeline for a specific recurring topic, they flip `auto_publish` + `target_status` on that topic and the host adapter publishes it directly on the next generate.

The Telegram bot is the day-to-day interface: `/file write about laravel queues` enqueues a topic, `/list` shows recent topics, `/file 42` force-generates a draft for topic #42, `/spike 42` rejects.

---

## Install

```bash
composer require giorgigrdzelidze/laravel-stringer
php artisan migrate
```

The package registers its own service provider via `extra.laravel.providers`. After migrate:

```bash
php artisan db:seed --class=Stringer\\Laravel\\Database\\Seeders\\StringerDefaultPromptsSeeder
php artisan db:seed --class=Stringer\\Laravel\\Database\\Seeders\\StringerDefaultContentFieldsSeeder
```

(The seeders are also auto-run on the first console boot if their tables are empty — the explicit `db:seed` is for forced re-seeds.)

---

## Configure

### Environment variables

| Variable | Default | What it does |
|----------|---------|--------------|
| `STRINGER_LLM_DRIVER` | `gemini` | LLM driver. One of: `gemini`, `claude`, `openai`, `groq`. |
| `STRINGER_GEMINI_API_KEY` | — | Required when driver is `gemini`. |
| `STRINGER_CLAUDE_API_KEY` | — | Required when driver is `claude`. |
| `STRINGER_OPENAI_API_KEY` | — | Required when driver is `openai`. |
| `STRINGER_GROQ_API_KEY` | — | Required when driver is `groq`. |
| `GEMINI_MODEL` | `gemini-2.0-flash` | Per-driver model identifier. |
| `CLAUDE_MODEL` | `claude-sonnet-4-5` | |
| `OPENAI_MODEL` | `gpt-4o-mini` | |
| `GROQ_MODEL` | `llama-3.3-70b-versatile` | |
| `STRINGER_TELEGRAM_ENABLED` | `true` | Skips the webhook route registration when false. |
| `STRINGER_TELEGRAM_BOT_TOKEN` | — | Bot token from BotFather. |
| `STRINGER_TELEGRAM_WEBHOOK_SECRET` | — | 16+ alphanumeric (`[A-Za-z0-9_-]{16,}`) used as the URL secret on `/webhooks/telegram/{secret}`. |
| `STRINGER_TELEGRAM_ALLOWED_CHAT_IDS` | — | Comma-separated chat ids allowed to drive the bot. Non-allowlisted chats get a `200 OK` with empty body. |
| `STRINGER_AUTO_GENERATE_CRON` | `0 9 * * 1` | When the weekly auto-generate job fires. |
| `STRINGER_AUTO_GENERATE_TZ` | `Asia/Tbilisi` | Timezone for the cron above. |
| `STRINGER_ARTICLE_MODEL` | — | FQN of the host's article model — required only if you use `BlogTopic::article()`. |
| `STRINGER_SEED_DEFAULTS_ON_BOOT` | `true` | Auto-run seeders if tables are empty. Set false in containers that boot many short-lived consoles. |

### Filament admin

Install the Filament v5 admin panel and register the plugin:

```php
// app/Providers/Filament/AdminPanelProvider.php
use Stringer\Laravel\Filament\StringerPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(StringerPlugin::make());
}
```

Four surfaces appear under the **Stringer** navigation group:

- **Topics** — the queue. Edit a topic, click *Generate Now* to dispatch a draft job, *Spike* to reject.
- **Prompts** — DB-backed prompt templates. Two seeded rows (`draft`, `translate`) with `{{placeholder}}` substitution; edit content / duplicate / toggle active without a deploy.
- **Content Fields** — the field schema. Five seeded rows (`title`, `excerpt`, `body`, `tags`, `category`) covering the seven `FieldType` values. Add a new field and the LLM will be asked for it on the next draft.
- **Settings** — voice card, body word cap, tag count, schedule cron + timezone, allowed chat ids overlay.

---

## Usage

### Telegram

Set the bot's webhook to `https://your-host.example/webhooks/telegram/{your-webhook-secret}`, then talk to it:

| Trigger | Behavior |
|---------|----------|
| `/help` | List commands (Georgian by default — override the strings by binding your own command classes). |
| `/list` | Show the 10 most recent topics with id, status, and a truncated hint. |
| `/file` | Show pending (queued) topics. |
| `/file write about X` | Enqueue a new `Manual` topic with the hint *"write about X"*. |
| `/file 42` | Force-dispatch `GenerateDraftJob` for topic #42. |
| `/draft …` | Alias for `/file …`. |
| `/spike 42` | Mark topic #42 as Rejected. |
| any other text | Enqueued as a new `Manual` topic. |

### Scheduler

The provider auto-binds the weekly job: it picks the oldest `Queued` topic, or synthesizes an `Auto`-source topic from the host's first project / repository title when nothing's queued. Override the cadence via `STRINGER_AUTO_GENERATE_CRON` / `STRINGER_AUTO_GENERATE_TZ`.

---

## Host integration

`stringer` is opinionated about the *pipeline* and agnostic about *where the drafts land*. Two contracts bind the package to the host's Eloquent model:

```php
// app/Stringer/Adapters/ArticleTarget.php

use App\Models\Article;
use App\Models\ArticleCategory;
use Illuminate\Database\Eloquent\Model;
use Stringer\Laravel\Contracts\ContentTarget;
use Stringer\Laravel\DataObjects\LocalizedDraft;
use Stringer\Laravel\Enums\FieldType;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;

final class ArticleTarget implements ContentTarget
{
    public function __construct(
        private readonly Article $articles,
        private readonly ArticleCategory $categories,
    ) {}

    public function write(LocalizedDraft $draft, ?BlogTopic $topic = null): Article
    {
        $article = $this->articles->newInstance();
        $fields = StringerContentField::query()->where('is_active', true)->get()->keyBy('name');

        $pendingTags = [];
        foreach ($draft->fields as $name => $value) {
            $field = $fields->get($name);
            if (! $field) { continue; }

            match ($field->type) {
                FieldType::TranslatableString,
                FieldType::TranslatableMarkdown => $article->setTranslations($name, $value),
                FieldType::TagList   => $pendingTags[$name] = $value,
                FieldType::Category  => $this->assignCategory($article, $value),
                FieldType::Integer   => $article->{$name} = (int) $value,
                default              => $article->{$name} = $value,
            };
        }

        // G1: per-topic opt-in. Default: draft + publish_at=null.
        $article->status = $topic?->target_status ?? 'draft';
        $article->publish_at = ($topic && $topic->auto_publish) ? now() : null;

        $article->save();
        foreach ($pendingTags as $tags) { $article->syncTags($tags); }

        return $article;
    }

    private function assignCategory(Article $article, ?string $slug): void
    {
        if (! $slug) { return; }
        if ($category = $this->categories->where('slug', $slug)->first()) {
            $article->article_category_id = $category->id;
        }
    }

    public function editUrl(Model $record): string
    {
        return \App\Filament\Resources\ArticleResource::getUrl('edit', ['record' => $record]);
    }
}
```

```php
// app/Stringer/Adapters/PublicContextBuilder.php

use App\Models\{Article, ArticleCategory, Project, Repository};
use Stringer\Laravel\Contracts\ContextBuilder;

final class PublicContextBuilder implements ContextBuilder
{
    public function __construct(
        private readonly Article $articles,
        private readonly Project $projects,
        private readonly Repository $repositories,
        private readonly ArticleCategory $categories,
    ) {}

    public function build(): array
    {
        return [
            'articles' => $this->articles->published()->latest()->limit(20)->get()
                ->map(fn ($a) => ['title' => $a->getTranslation('title', 'en'),
                                  'excerpt' => $a->getTranslation('excerpt', 'en')])->all(),
            'projects' => /* same shape */ [],
            'repositories' => /* same shape */ [],
            'categories' => $this->categories->ordered()->get()
                ->map(fn ($c) => ['name' => $c->name, 'slug' => $c->slug, 'description' => $c->description])
                ->all(),
        ];
    }
}
```

Bind both in a host service provider:

```php
$this->app->bind(\Stringer\Laravel\Contracts\ContentTarget::class, ArticleTarget::class);
$this->app->bind(\Stringer\Laravel\Contracts\ContextBuilder::class, PublicContextBuilder::class);
```

The `ContextBuilder` constructor's type signature **is** the G3 architectural enforcement: type-hinting only public-content + public-taxonomy models prevents finance / admin / user records from leaking into LLM context.

### Dynamic fields

Add a row to `StringerContentField` via Filament — say, `seo_meta_description` of type `TranslatableString`, `is_required=false`. On the next draft the LLM is asked for it; your `ContentTarget` iterator picks it up and writes via `setTranslations`. No package code changes.

### Category resolution

The seeded `category` field is `FieldType::Category`. The LLM emits a slug like `"backend"`. `ArticleTarget::assignCategory` resolves the slug against `ArticleCategory` and sets `article_category_id`. A slug that doesn't match an existing row is silently skipped — the host model keeps the default category.

---

## Guardrails

The package enforces a small set of architectural rules:

- **Default writes drafts.** Auto-publish is per-topic opt-in via `BlogTopic.auto_publish` + `target_status`.
- **Never touches `schema_json`.** The host content model regenerates JSON-LD at render time; package writes would conflict.
- **No host coupling in `src/`.** Only the `Contracts/` interfaces are touched by host code.
- **No public auth on the webhook.** Path-secret authentication + chat-id allowlist; never `auth:` middleware.
- **Public taxonomy only in `ContextBuilder`.** The constructor type signature prevents finance / admin / user models from being injected.

---

## License

MIT. See [LICENSE](LICENSE).
