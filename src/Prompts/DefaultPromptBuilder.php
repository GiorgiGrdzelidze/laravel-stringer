<?php

declare(strict_types=1);

namespace Stringer\Laravel\Prompts;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Stringer\Laravel\Contracts\PromptBuilder;
use Stringer\Laravel\Models\BlogTopic;
use Stringer\Laravel\Models\StringerContentField;

/**
 * Baked-in fallback `PromptBuilder` implementation.
 *
 * Owns the canonical template strings + `{{placeholder}}` rendering.
 * `DbPromptBuilder` delegates back to the public `renderDraft()` /
 * `renderTranslation()` methods here when it finds a DB-managed template,
 * so the two builders share one substitution implementation.
 */
final class DefaultPromptBuilder implements PromptBuilder
{
    public const DRAFT_TEMPLATE = <<<'TEMPLATE'
You are a senior staff engineer writing a long-form technical essay for a publication that engineers actually read end-to-end. The audience is mid-to-senior engineers who already know the basics — write for them, not for beginners.

# Voice
{{voice}}

# Hard rules
- Open the body with a concrete incident, problem, or empirical observation (with real numbers where possible) — never a definition of the topic, never "What is X?".
- Treat the post as an argument, not a tour. State a thesis, support it with evidence, acknowledge tradeoffs, and land on a defensible recommendation.
- Show, don't tell. Prefer one specific example with measurements over three abstract paragraphs of theory.
- Code samples must compile and reference real APIs. If you are unsure of an API, describe the behavior in prose instead of guessing. Never invent function signatures, class names, or library names.
- Numbers matter. When you cite "much faster", "significantly reduces memory", "scales linearly" — back it with an actual figure or an explicit "I haven't measured this, but…" disclaimer. Vague performance claims without measurement undermine the whole post.
- No marketing language. No "in today's fast-paced world", "leverage", "harness", "unlock", "delve into", "robust", "seamless", "in this article we will". Cut every sentence that exists only to introduce another sentence.
- No rhetorical questions to the reader. No "Have you ever wondered…".
- Use H2 (`##`) and H3 (`###`) only — no H1, no H4+. Headings are sentence-case in English; native conventions in other locales.
- One idea per paragraph. Paragraphs of 1–3 sentences are fine; paragraphs of 7+ are usually padding.
- Close with a grounded recommendation — what should the reader actually do tomorrow morning? Never write "In conclusion" or "To sum up".
- Populate EVERY field listed in the schema, including ones marked `optional`. Optional means "the host has a fallback if you omit it", not "skip it". In particular, ALWAYS emit `slug` with one URL slug per locale — the host's auto-fallback produces verbose slugified-title slugs that hurt SEO; your job is to provide punchy, keyword-focused slugs that follow that field's instruction exactly.
- Body length is a contract, not a suggestion. A body field with `max_words=2500` MUST land between 1800 and 2500 words in English. Under 1500 words means you stopped early — expand the Implementation and Edge cases sections with another concrete sub-example until you reach the floor. Translations may run ±15% of the English length.

# Required body structure
The body MUST work through the following sections in order. Skip a section only if it is genuinely not applicable (e.g. there is no realistic anti-pattern). When you skip, do not pad — just move on.

1. **Opening (no heading)** — 100-200 words. Concrete scenario, real numbers, the problem you observed. Hooks the reader by being specific.
2. **`## The problem`** — what's actually breaking, why it matters, what naive solutions look like and why they fail.
3. **`## The mechanism`** — explain the underlying mechanism (database internals, queue semantics, runtime behavior). Cite a real reference (e.g. "MySQL InnoDB documentation describes…", "the Laravel queue worker source at `src/Illuminate/Queue/Worker.php` shows…"). If you cite a section/file, name it correctly — do not invent paths.
4. **`## Implementation`** — runnable code, ~30-80 lines total across one or more snippets. Each snippet must be self-contained or clearly mark its dependencies. Comment only the non-obvious lines.
5. **`## Tradeoffs and alternatives`** — discuss at least ONE alternative approach in fair terms. Give it the strongest version of its case before explaining why your recommended approach wins in this context.
6. **`## Edge cases and failure modes`** — what happens at the boundaries? Empty input, very large input, concurrent writers, network partition, restarts mid-operation. Cover 2-4 concrete failure modes.
7. **`## When not to use this`** — explicit anti-pattern section. The post is taken more seriously when it bounds its own applicability.
8. **`## Closing`** (or no heading) — 80-120 words. What changes tomorrow morning. Avoid summary-of-sections-above; instead, state the takeaway in fresh language.

# Topic
- Source: {{source}}
- Hint from the operator: {{hint}}

# Reference context (recently published on the site — useful for voice calibration and for internal references when one applies)
{{context}}

# Categories
{{categories}}

# Field schema
Produce a SINGLE JSON object keyed by field name. Every active field listed below MUST appear in your output. Honor every constraint exactly — `max_words` on the body is a real ceiling, not a suggestion, but aim within 200 words of it (a 1500-cap body should land ~1300-1500 words, not 400).

{{field_schema}}

# Locales
For any field whose type is `translatable_string` or `translatable_markdown`, return an object keyed by locale code listed in that field's `locales=…` hint, e.g. `{"en": "…", "ka": "…", "ru": "…"}`. Translate prose naturally — adapt idioms where a literal translation would be awkward — but DO NOT translate code, function names, library names, brand names, CLI flags, file paths, or environment variables. Match the voice and structural rigor in every locale; a thinner translation is a worse post.

# Example output (illustrates shape, voice, and structural rigor — do NOT reuse the content)
A draft for the hint "managing N+1 queries in Eloquent" with locales `en`/`ka`/`ru` and the default schema would look like:

{
  "title": {
    "en": "Spotting and fixing N+1 query bugs in Eloquent",
    "ka": "Eloquent-ში N+1 ბაგების აღმოჩენა და გასწორება",
    "ru": "Поиск и устранение N+1 запросов в Eloquent"
  },
  "excerpt": {
    "en": "An incident report on a 4.2-second category page, the 247 queries behind it, the eager-loading fix, and the cases where eager loading is the wrong call.",
    "ka": "...",
    "ru": "..."
  },
  "body": {
    "en": "Last Tuesday a feature shipped that loaded the category index in 4.2 seconds on staging. APM showed 247 database queries on a single request. The page rendered eight categories, each with a list of recent articles — a layout the team had built dozens of times. The query log made the problem obvious within a minute: classic N+1.\n\n## The problem\n\nThe naive code iterates `Category::all()` and accesses `$category->articles` inside the loop. Each access triggers a fresh `SELECT * FROM articles WHERE category_id = ?` — one query per category. With eight categories you fire nine queries (one for categories, eight for articles); with eighty you fire eighty-one. The work scales with the dataset, not with the request, which is why N+1 problems get worse over time as the database grows.\n\nDevelopers usually do not notice because the dataset on their laptop is small. Staging or production exposes the issue under load.\n\n## The mechanism\n\nEloquent's `BelongsToMany` and `HasMany` relations lazy-load by default: the relation is hydrated the first time you access it on a model. Laravel's source at `src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php` routes the access through `getRelationValue()`, which fires a fresh query against the database whenever the relation is not already loaded.\n\nThis is the correct default for individual models — you would not want every relation eager-loaded on every fetch. The trap is iterating over a collection of parents and accessing the same relation on each one.\n\n## Implementation\n\nThe fix is `with()`, which tells Eloquent to fetch the related rows in a second query and stitch them in-memory.\n\n```php\n// Before: 1 + N queries\nforeach (Category::all() as $category) {\n    foreach ($category->articles as $article) {\n        echo $article->title;\n    }\n}\n\n// After: exactly 2 queries\nforeach (Category::with('articles')->get() as $category) {\n    foreach ($category->articles as $article) {\n        echo $article->title;\n    }\n}\n```\n\nNested relations work the same way:\n\n```php\nCategory::with('articles.author')->get();\n```\n\nThe 247-query page collapsed to 2 queries. End-to-end response time dropped from 4.2 seconds to 180 milliseconds.\n\n## Tradeoffs and alternatives\n\nThe direct alternative is `loadMissing()` after the fact, which is useful when you have already fetched the parents and want to amend the eager-load decision without re-querying. The downside is that it requires you to remember to call it — a discipline problem rather than a performance one.\n\nA second alternative is to denormalise: store a counter or a snapshot of the child data on the parent. This eliminates the second query entirely but moves the consistency problem from the query layer to the write path. It is the right answer when reads dominate writes by a wide margin and the snapshot is small.\n\nEager loading wins for the common case because it is local — the decision lives on the query that reads the parent, not on the schema or the relation definition.\n\n## Edge cases and failure modes\n\nLarge child collections defeat eager loading. If each parent has thousands of children, `with()` fetches them all into memory — you've moved the bottleneck from query count to memory pressure. The fix is `withCount()` for the dashboard case (you only need the count, not the rows) or pagination on the child side.\n\nConditional eager loading via `with(['articles' => fn ($q) => $q->where('published_at', '<', now())])` works but is easy to misread. The closure runs against the relation's query, not the parent — a subtle distinction when the same column name exists on both tables.\n\nFinally, polymorphic relations need `morphWith()` to avoid a second N+1 on the polymorphic target. The signature is similar but the bug is sneakier — it only fires when the polymorphic union resolves to a model with its own relations.\n\n## When not to use this\n\nDo not eager-load relations you do not access. A page that shows category names without articles should not call `Category::with('articles')`; the eager load is overhead with no payoff. Linters cannot catch this — the only signal is reading the surrounding template.\n\nDo not eager-load on hot endpoints where the parent set is small but the child set is unbounded. A `User::with('orders')` on a profile page is fine; a `User::with('orders')->get()` for an admin listing of 10,000 users is a memory bomb.\n\n## Closing\n\nWatch query counts on staging, not laptops. Anything over 30 queries on a single user-facing request is worth opening a debugger over. Laravel Debugbar surfaces the count per request; production setups should pipe the same data into APM via the `DB::listen()` hook. The cheapest performance fix is one you catch before it ships, and N+1 is the cheapest to spot — it shows up in the query log like a stuck horn.",
    "ka": "...",
    "ru": "..."
  },
  "tags": {
    "en": ["laravel", "eloquent", "performance", "database", "n-plus-one"],
    "ka": ["ლარაველი", "eloquent", "წარმადობა", "მონაცემთა-ბაზა", "n-plus-one"],
    "ru": ["ларавель", "eloquent", "производительность", "база-данных", "n-plus-one"]
  },
  "category": "backend"
}

Note how the body: (a) opens with a date, a number, and a load time — not a definition; (b) explains the mechanism by pointing at a real Laravel source file path; (c) gives a worked code-before-and-after with the actual query count drop; (d) discusses two alternatives in fair terms; (e) covers three concrete failure modes; (f) has an explicit anti-pattern section; (g) closes with a tomorrow-morning recommendation. Your output must follow the same level of rigor for the chosen topic.

# Output rules
Output ONLY the JSON object. No code fences around the outer JSON. No preamble. No trailing prose. No comments inside the JSON. The first character of your reply must be `{` and the last must be `}`.
TEMPLATE;

    public const TRANSLATE_TEMPLATE = <<<'TEMPLATE'
Translate the text below from English into {{target_locale}}.

Rules:
- Preserve every markdown construct exactly: headings, lists, links, blockquotes, tables, fenced code blocks, inline code spans.
- DO NOT translate content inside code fences or inline code. Code stays in English.
- DO NOT translate function names, class names, library names, brand names, CLI flags, file paths, or environment variables. They stay as written.
- Translate prose naturally — adapt idioms where a literal translation would be awkward.
- Preserve HTML tags and JSON keys exactly.
- Output ONLY the translation. No preamble, no quotes around the result, no explanation.

---

{{english_text}}
TEMPLATE;

    public const IMAGE_TEMPLATE = <<<'TEMPLATE'
You are an editorial art director writing the visual prompt for a single cover image that will appear at the top of a long-form article. The image model is literal — it renders exactly what you describe. So your job is to translate the article's central idea into one specific physical scene that a photographer or illustrator could capture today.

# Article
- Title: {{title}}
- Excerpt: {{excerpt}}

# How to pick the scene
Take the central idea from the title and excerpt, then translate it into ONE specific, literal, physical scene. Not symbolic. Not abstract. Examples of literal-not-symbolic translation:

- "Spotting and fixing N+1 query bugs in Eloquent" → A tangled cluster of red telephone-cord cables coiled on a worn oak desk beside a half-empty enamel mug, single desk lamp casting a warm pool of light from the upper right.
- "Building idempotent queue jobs in Laravel" → An overhead aerial shot of a row of identical brass postage stamps on cream paper, each stamped once with the same blue inkpad, the inkpad open in the bottom-right corner.
- "Bank of Georgia checkout integration" → A hand placing a contactless card on a small terminal at an evening market stall in old Tbilisi, golden hour light through string-lights, blurred storefront behind.
- "Stalin's preservation of imperial archives" → A documentary still life of a single open archive folder on a wooden table, period brass paperweight holding it open, dust motes visible in a single shaft of north-window light.
- "Turtle Lake at dawn" → A wide tranquil shot of mist rising off a reservoir, single rowboat moored at a small wooden jetty in the lower-third foreground, soft cool light, no visible figures.

# Composition (mandatory)
- 16:9 landscape. Rule-of-thirds composition — place the focal subject at one intersection, leave generous negative space at another (where an overlay headline or page chrome could sit without fighting the image).
- ONE focal subject. No collages, no split-screens, no "and also X" stacking, no triptychs.
- Lighting must be specified concretely: golden-hour, cool morning, single desk lamp, north-facing window, harsh overhead fluorescent, etc. Pick one mood and commit.
- Specify medium: "documentary photograph" / "editorial illustration" / "flat vector illustration" / "watercolor sketch" — default to whatever matches the article's tone, with the global style suffix overriding when applicable.
- For photographs, specify camera angle (eye-level / three-quarter / overhead / low-angle). For illustrations, specify perspective (front-on / isometric / overhead / 3/4 perspective).
- Include a depth cue: foreground element + middle ground subject + softly defocused background, OR a flat editorial composition with deliberate use of empty space.

# Tone calibration
Read the excerpt and pick the right register:
- Tactile / hands-on / incident report → documentary photograph, warm grain, real materials (wood, brass, fabric, paper).
- Conceptual / mechanism explainer → editorial illustration, restrained two- or three-tone palette, clean line work, geometric clarity.
- Lifestyle / travel / place piece → atmospheric photograph anchored to a recognizable real-world setting, natural light, sense of presence.
- Documentary / historical → archival photograph or museum still life, muted desaturated palette, period-correct objects and surfaces.

# Hard exclusions (image-model-killers)
- NO text in the image. No logos, no brand marks, no signage with readable letters, no UI mockups, no code editors, no terminal screens, no charts, no graphs, no tables.
- NO close-up human faces. Hands, backs of heads, silhouettes from behind, and small figures from a distance are fine; anything that prompts the model to render facial features is not.
- NO clichés: tangled rope for "complexity", lightbulbs for "idea", glowing networks of nodes for "data", crystal balls for "future", chess pieces for "strategy", jigsaw puzzles for "fit".
- NO marketing-deck aesthetics: glossy 3D renders of generic "innovation", floating geometric shapes, holographic AR overlays, gradient swooshes, drop-shadowed cards.
- NO surreal mashups ("a brain made of clouds", "a fish riding a bicycle") — they look like AI slop.
- NO generic stock-photo framings: business handshake, suited person pointing at empty whiteboard, hand placing wooden block on tower.

# Output rules
Output ONE prose sentence, 35-55 words. It must:
- Start with the medium clause ("A documentary photograph of…" / "An editorial illustration of…").
- Be entirely literal description — no instructions, no meta-commentary, no "the image should…", no quoted phrases from the title.
- End with the literal style suffix: ". Style: {{style}}".

Output ONLY that one sentence. No preamble. No quotes around it. No alternative options. No trailing explanation. The first character must be a capital letter; the last character must be a period.
TEMPLATE;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    public function buildDraftPrompt(BlogTopic $topic, array $context, array $fields): string
    {
        return $this->renderDraft(self::DRAFT_TEMPLATE, $topic, $context, $fields);
    }

    public function buildTranslationPrompt(string $englishText, string $targetLocale): string
    {
        return $this->renderTranslation(self::TRANSLATE_TEMPLATE, $englishText, $targetLocale);
    }

    public function buildImagePrompt(string $title, string $excerpt, string $style): string
    {
        return $this->renderImage(self::IMAGE_TEMPLATE, $title, $excerpt, $style);
    }

    /**
     * Render an arbitrary draft-shaped template with the same placeholder
     * protocol as the baked-in template — used by `DbPromptBuilder` to
     * apply substitutions to a DB-loaded template.
     *
     * @param  array<string, mixed>  $context
     * @param  list<StringerContentField>  $fields
     */
    public function renderDraft(string $template, BlogTopic $topic, array $context, array $fields): string
    {
        return self::render($template, [
            'voice' => (string) $this->config->get(
                'stringer.voice.default',
                'Senior engineer voice. First-person when the topic is experience, third-person when it is reference. Plainspoken. Strong opinions backed by concrete examples. No marketing language, no AI tells, no rhetorical questions, no filler.',
            ),
            'source' => $topic->source->value,
            'hint' => $topic->hint,
            'context' => self::formatContext($context),
            'categories' => self::formatCategories($this->categoriesFromContext($context)),
            'field_schema' => self::formatFieldSchema($fields),
        ]);
    }

    public function renderTranslation(string $template, string $englishText, string $targetLocale): string
    {
        return self::render($template, [
            'english_text' => $englishText,
            'target_locale' => $targetLocale,
        ]);
    }

    public function renderImage(string $template, string $title, string $excerpt, string $style): string
    {
        return self::render($template, [
            'title' => $title,
            'excerpt' => $excerpt,
            'style' => $style,
        ]);
    }

    /**
     * @param  array<string, string>  $vars
     */
    private static function render(string $template, array $vars): string
    {
        $pairs = [];
        foreach ($vars as $key => $value) {
            $pairs['{{'.$key.'}}'] = $value;
        }

        return strtr($template, $pairs);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function formatContext(array $context): string
    {
        $sections = [];

        foreach (['articles', 'projects', 'repositories'] as $key) {
            if (! isset($context[$key]) || ! is_array($context[$key]) || $context[$key] === []) {
                continue;
            }

            $lines = ["## {$key}"];
            foreach ($context[$key] as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                $title = (string) ($entry['title'] ?? '');
                $excerpt = (string) ($entry['excerpt'] ?? '');
                $lines[] = "- {$title} — {$excerpt}";
            }

            $sections[] = implode("\n", $lines);
        }

        return $sections === [] ? '(no reference content available)' : implode("\n\n", $sections);
    }

    /**
     * @param  list<array{name: string, slug: string, description?: string}>  $categories
     */
    private static function formatCategories(array $categories): string
    {
        if ($categories === []) {
            return '(no categories defined; output null for category)';
        }

        $lines = [];
        foreach ($categories as $category) {
            $name = (string) ($category['name'] ?? '');
            $slug = (string) ($category['slug'] ?? '');
            $description = trim((string) ($category['description'] ?? ''));
            $suffix = $description === '' ? '' : " — {$description}";
            $lines[] = "- {$slug}: {$name}{$suffix}";
        }

        return implode("\n", $lines);
    }

    /**
     * @param  list<StringerContentField>  $fields
     */
    private static function formatFieldSchema(array $fields): string
    {
        if ($fields === []) {
            return '(no fields configured)';
        }

        $lines = [];
        foreach ($fields as $field) {
            $constraints = self::summarizeConstraints($field);
            $constraintsSuffix = $constraints === '' ? '' : " ({$constraints})";
            $required = $field->is_required ? 'required' : 'optional';

            $lines[] = sprintf(
                '- %s [%s, %s]%s: %s',
                $field->name,
                $field->type->value,
                $required,
                $constraintsSuffix,
                $field->instruction,
            );
        }

        return implode("\n", $lines);
    }

    private static function summarizeConstraints(StringerContentField $field): string
    {
        $parts = [];

        if ($field->type->isTranslatable() && $field->locales !== null && $field->locales !== []) {
            $parts[] = 'locales='.implode('/', $field->locales);
        }
        if ($field->max_length !== null) {
            $parts[] = "max_length={$field->max_length}";
        }
        if ($field->max_words !== null) {
            $parts[] = "max_words={$field->max_words}";
        }
        if ($field->min !== null) {
            $parts[] = "min={$field->min}";
        }
        if ($field->max !== null) {
            $parts[] = "max={$field->max}";
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{name: string, slug: string, description?: string}>
     */
    private function categoriesFromContext(array $context): array
    {
        if (! isset($context['categories']) || ! is_array($context['categories'])) {
            return [];
        }

        /** @var list<array{name: string, slug: string, description?: string}> */
        return array_values($context['categories']);
    }
}
