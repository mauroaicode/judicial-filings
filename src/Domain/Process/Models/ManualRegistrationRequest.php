<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ManualRegistrationRequestReason;
use Src\Domain\Process\Enums\ManualRegistrationRequestStatus;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Shared\Traits\Uuid;

/**
 * Solicitud de alta manual cuando el abogado no puede registrar por consulta automática.
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $app_user_id
 * @property-read string $process_number
 * @property-read ManualRegistrationRequestReason $reason
 * @property-read ManualRegistrationRequestStatus $status
 * @property-read ProcessLawyerRole|null $lawyer_role
 * @property-read string|null $process_class
 * @property-read string|null $court
 * @property-read string|null $speaker
 * @property-read string|null $subclass_process
 * @property-read string|null $location
 * @property-read list<array{name: string, identification: string|null}>|null $plaintiffs
 * @property-read list<array{name: string, identification: string|null}>|null $defendants
 * @property-read list<array{name: string, identification: string|null}>|null $other_subjects
 * @property-read int $unassigned_actions_count
 * @property-read bool $discord_notified
 * @property-read Carbon|null $resolved_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class ManualRegistrationRequest extends Model
{
    use Uuid;

    protected $table = 'manual_registration_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'app_user_id',
        'process_number',
        'reason',
        'status',
        'lawyer_role',
        'process_class',
        'court',
        'speaker',
        'subclass_process',
        'location',
        'plaintiffs',
        'defendants',
        'other_subjects',
        'unassigned_actions_count',
        'discord_notified',
        'resolved_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reason' => ManualRegistrationRequestReason::class,
        'status' => ManualRegistrationRequestStatus::class,
        'lawyer_role' => ProcessLawyerRole::class,
        'plaintiffs' => 'array',
        'defendants' => 'array',
        'other_subjects' => 'array',
        'unassigned_actions_count' => 'integer',
        'discord_notified' => 'boolean',
        'resolved_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<AppUser, $this>
     */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class);
    }
}
