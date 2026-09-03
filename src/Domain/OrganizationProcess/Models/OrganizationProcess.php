<?php

declare(strict_types=1);

namespace Src\Domain\OrganizationProcess\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\User\Models\User;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $process_id
 * @property-read Carbon $interest_date
 * @property-read bool $is_active
 * @property-read OrganizationProcessStatus $status
 * @property-read ProcessLawyerRole|null $lawyer_role
 * @property-read string|null $inactivity_alert_level
 * @property-read string|null $deleted_by
 * @property-read Carbon $created_at
 * @property-read Carbon|null $updated_at
 * @property-read Carbon|null $deleted_at
 * @property-read Organization $organization
 * @property-read Process $process
 */
class OrganizationProcess extends Pivot
{
    use SoftDeletes;
    use Uuid;

    protected $table = 'organization_processes';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'process_id',
        'interest_date',
        'is_active',
        'status',
        'lawyer_role',
        'inactivity_alert_level',
        'deleted_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interest_date' => 'date',
            'is_active' => 'boolean',
            'status' => OrganizationProcessStatus::class,
            'lawyer_role' => ProcessLawyerRole::class,
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Create or restore the org↔process tracking row and mark it active.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function syncActiveLink(string $organizationId, string $processId, array $attributes = []): self
    {
        $existing = static::withTrashed()
            ->where('organization_id', $organizationId)
            ->where('process_id', $processId)
            ->first();

        $payload = array_merge([
            'organization_id' => $organizationId,
            'process_id' => $processId,
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE,
            'deleted_by' => null,
        ], $attributes);

        if ($existing instanceof self) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return static::query()->create($payload);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
