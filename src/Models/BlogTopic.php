<?php

declare(strict_types=1);

namespace Stringer\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;
use Stringer\Laravel\Enums\TopicSource;
use Stringer\Laravel\Enums\TopicStatus;

/**
 * Eloquent record for a single drafting topic.
 *
 * @property int $id
 * @property string $hint
 * @property TopicStatus $status
 * @property TopicSource $source
 * @property string|null $source_ref_type
 * @property int|null $source_ref_id
 * @property int|null $article_id
 * @property string|null $generated_by
 * @property string|null $last_error
 * @property int|null $requested_by_chat_id
 * @property bool $auto_publish
 * @property string|null $target_status
 * @property Carbon|null $drafted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class BlogTopic extends Model
{
    // Supported writes go through Services\TopicQueue; direct mass-assignment
    // is not the documented path, so the broad guard is intentional.
    protected $guarded = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'status' => TopicStatus::class,
        'source' => TopicSource::class,
        'auto_publish' => 'boolean',
        'target_status' => 'string',
        'drafted_at' => 'datetime',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'auto_publish' => false,
        'target_status' => 'draft',
    ];

    public function __construct(array $attributes = [])
    {
        $this->setTable(config('stringer.tables.blog_topics', 'blog_topics'));
        parent::__construct($attributes);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function sourceRef(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * BelongsTo the host's article model. Requires `stringer.models.article`
     * to name the host's Eloquent class — the package has no Article model
     * of its own.
     *
     * @return BelongsTo<Model, $this>
     */
    public function article(): BelongsTo
    {
        $model = config('stringer.models.article');

        if (! is_string($model) || $model === '' || ! class_exists($model)) {
            throw new LogicException(
                "BlogTopic::article() needs config('stringer.models.article') to name the host's Article model class."
            );
        }

        return $this->belongsTo($model);
    }
}
