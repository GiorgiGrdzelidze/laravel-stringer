<?php

declare(strict_types=1);

namespace Stringer\Laravel\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stringer\Laravel\Enums\FieldType;

/**
 * DB-managed field schema editable via Filament.
 *
 * `DraftGenerator` reads the active rows in `sort_order` and tells the
 * LLM what to produce. `ArticleTarget` reads the same rows to dispatch
 * each field's value onto the host's content model by `FieldType`.
 *
 * @property int $id
 * @property string $name
 * @property FieldType $type
 * @property array<int, string>|null $locales
 * @property int|null $max_length
 * @property int|null $max_words
 * @property int|null $min
 * @property int|null $max
 * @property string $instruction
 * @property bool $is_required
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class StringerContentField extends Model
{
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'type' => FieldType::class,
        'locales' => 'array',
        'max_length' => 'integer',
        'max_words' => 'integer',
        'min' => 'integer',
        'max' => 'integer',
        'is_required' => 'boolean',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('stringer.tables.stringer_content_fields', 'stringer_content_fields'));
        parent::__construct($attributes);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
