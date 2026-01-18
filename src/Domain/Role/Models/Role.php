<?php

declare(strict_types=1);

namespace Src\Domain\Role\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $guard_name
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class Role extends SpatieRole
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'string',
        ];
    }
}
