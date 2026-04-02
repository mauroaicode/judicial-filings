<?php

declare(strict_types=1);

namespace Src\Domain\OrganizationProcess\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;

/**
 * @property-read string $organization_id
 * @property-read string $process_id
 * @property-read Carbon $interest_date
 * @property-read bool $is_active
 * @property-read ProcessLawyerRole|null $lawyer_role
 * @property-read string|null $inactivity_alert_level
 * @property-read Carbon $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read Process $process
 */
class OrganizationProcess extends Pivot
{
    protected $table = 'organization_processes';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass-assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'process_id',
        'interest_date',
        'is_active',
        'lawyer_role',
        'inactivity_alert_level',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interest_date' => 'date',
            'is_active' => 'boolean',
            'lawyer_role' => ProcessLawyerRole::class,
        ];
    }

    /**
     * Get the organization that owns this relationship.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the process that owns this relationship.
     *
     * @return BelongsTo<Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
