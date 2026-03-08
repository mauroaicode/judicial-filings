<?php

declare(strict_types=1);

namespace Src\Domain\Keyword\Models;

use Database\Factories\KeywordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Keyword\Enums\KeywordStatus;
use Src\Domain\Keyword\QueryBuilders\KeywordQueryBuilder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $name
 * @property-read string $keyword
 * @property-read KeywordStatus $status
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static KeywordQueryBuilder query()
 */
class Keyword extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'keywords';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'keyword',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KeywordStatus::class,
        ];
    }

    /**
     * Get the organization that owns the keyword.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): KeywordFactory
    {
        return KeywordFactory::new();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): KeywordQueryBuilder
    {
        return new KeywordQueryBuilder($query);
    }
}
