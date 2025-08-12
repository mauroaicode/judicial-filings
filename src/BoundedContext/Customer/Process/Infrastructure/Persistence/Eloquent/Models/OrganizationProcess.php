<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models;

use Carbon\Carbon;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Process;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $process_id
 * @property-read Carbon $interest_date
 * @property-read bool $is_active
 * @property-read Carbon $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Process $process
 */
class OrganizationProcess extends Pivot
{
    use HasUuids;

    protected $table = 'organization_processes';

    protected $fillable = [
        'organization_id',
        'process_id',
        'interest_date',
        'is_active',
    ];

    protected $casts = [
        'interest_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the organization that owns this relationship.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the process that owns this relationship.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
