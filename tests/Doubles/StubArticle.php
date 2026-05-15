<?php

declare(strict_types=1);

namespace Stringer\Laravel\Tests\Doubles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal Eloquent stand-in for the host's Article model in tests.
 *
 * Provides a real `articles` table so `BlogTopic::article()` can resolve
 * the BelongsTo relation, plus a `getTranslation` shim so the cover job's
 * title / excerpt lookup works without pulling Spatie\Translatable into
 * the test dependency graph.
 */
final class StubArticle extends Model
{
    protected $table = 'articles';

    protected $guarded = [];

    public $timestamps = false;

    public function getTranslation(string $key, string $locale): string
    {
        $value = $this->{$key} ?? '';

        return is_string($value) ? $value : '';
    }

    public static function ensureSchema(): void
    {
        if (! Schema::hasTable('articles')) {
            Schema::create('articles', function (Blueprint $table): void {
                $table->id();
                $table->string('title')->nullable();
                $table->text('excerpt')->nullable();
            });
        }
    }
}
