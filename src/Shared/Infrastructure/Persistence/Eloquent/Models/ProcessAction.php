<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ProcessActionFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Core\Shared\Infrastructure\Traits\Uuid;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read string $process_id
 * @property-read int $action_registration_id
 * @property-read Carbon $action_date
 * @property-read string $action
 * @property-read string|null $annotation
 * @property-read Carbon|null $start_date
 * @property-read Carbon|null $end_date
 * @property-read Carbon $registration_date
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Process $process
 * @property-read Organization $organizations
 * @property-read Organization $viewedByOrganizations
 * @property-read Organization $notifiedOrganizations
 */
class ProcessAction extends Model
{
    use HasFactory, Uuid;

    protected $table = 'process_actions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'process_id',
        'action_registration_id',
        'action_date',
        'action',
        'annotation',
        'start_date',
        'end_date',
        'registration_date',
    ];

    protected $casts = [
        'action_date' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_date' => 'date',
    ];

    /**
     * Get the process that owns the action.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * Get the organizations that have access to this action.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_process_action')
            ->withPivot(['is_viewed', 'is_notified', 'viewed_at', 'notified_at'])
            ->withTimestamps();
    }

    /**
     * Get organizations that have viewed this action.
     */
    public function viewedByOrganizations()
    {
        return $this->organizations()->wherePivot('is_viewed', true);
    }

    /**
     * Get organizations that have been notified about this action.
     */
    public function notifiedOrganizations()
    {
        return $this->organizations()->wherePivot('is_notified', true);
    }

    protected static function newFactory(): ProcessActionFactory
    {
        return ProcessActionFactory::new();
    }
}
