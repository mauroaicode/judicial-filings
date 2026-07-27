<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\QueryBuilders\ProcessDataSourceQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $slug
 * @property-read string $name
 * @property-read bool $is_active
 *
 * @method static ProcessDataSourceQueryBuilder query()
 */
class ProcessDataSource extends Model
{
    use Uuid;

    protected $table = 'process_data_sources';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'slug',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function newEloquentBuilder(mixed $query): ProcessDataSourceQueryBuilder
    {
        return new ProcessDataSourceQueryBuilder($query);
    }

    /**
     * @return HasMany<Process, $this>
     */
    public function processes(): HasMany
    {
        return $this->hasMany(Process::class, 'process_data_source_id');
    }

    public static function uuidForSlug(ProcessDataSourceSlug $slug): string
    {
        $id = static::query()->whereSlug($slug->value)->value('id');

        if ($id === null) {
            throw new \InvalidArgumentException(sprintf('Missing process_data_sources.slug=%s', $slug->value));
        }

        return (string) $id;
    }
}
