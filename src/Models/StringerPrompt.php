<?php

declare(strict_types=1);

namespace Stringer\Laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * DB-managed prompt template editable via Filament.
 *
 * Resolved at runtime by `DbPromptBuilder`. A missing row for a given
 * `(key, locale)` pair falls back to `DefaultPromptBuilder`'s baked-in
 * template, so the package keeps working on a fresh install before the
 * seeder has run.
 *
 * @property int $id
 * @property string $key
 * @property string|null $locale
 * @property string $content
 * @property string|null $description
 * @property array<int, string> $variables
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StringerPrompt extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('stringer.tables.stringer_prompts', 'stringer_prompts'));
        parent::__construct($attributes);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
