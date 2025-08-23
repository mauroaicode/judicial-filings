<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent\Models;

use Carbon\Carbon;
use Core\Shared\Infrastructure\Traits\Uuid;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Organization Model
 *
 * Represents an organization (natural or juridical person) that can follow judicial processes.
 *
 * @property-read string $id
 * @property-read string $name
 * @property-read string $type
 * @property-read string|null $identification
 * @property-read string|null $address
 * @property-read string|null $phone
 * @property-read string|null $email
 * @property-read string|null $contact_person
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class Organization extends Model
{
    use HasFactory, Uuid;

    protected $table = 'organizations';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'identification',
        'address',
        'phone',
        'email',
        'contact_person',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory|OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * Get the app users for the organization.
     */
    public function appUsers(): BelongsToMany
    {
        return $this->belongsToMany(AppUser::class, 'app_user_organization')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * Get the owners of the organization.
     */
    public function owners()
    {
        return $this->appUsers()->wherePivot('is_owner', true);
    }

    /**
     * Get the processes that this organization is following.
     */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(
            Process::class,
            'organization_processes',
            'organization_id',
            'process_id'
        )->withPivot(['interest_date', 'is_active'])->withTimestamps();
    }

    /**
     * Get the process actions that this organization has access to.
     */
    public function processActions(): BelongsToMany
    {
        return $this->belongsToMany(ProcessAction::class, 'organization_process_action')
            ->withPivot(['is_viewed', 'is_notified', 'viewed_at', 'notified_at'])
            ->withTimestamps();
    }

    /**
     * Get the process actions that this organization has viewed.
     */
    public function viewedProcessActions()
    {
        return $this->processActions()->wherePivot('is_viewed', true);
    }

    /**
     * Get the process actions that this organization has been notified about.
     */
    public function notifiedProcessActions()
    {
        return $this->processActions()->wherePivot('is_notified', true);
    }

    /**
     * Get the notification channels for this organization.
     */
    public function notificationChannels(): HasMany
    {
        return $this->hasMany(\Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel::class);
    }

    /**
     * Get the active notification channels for this organization.
     */
    public function activeNotificationChannels()
    {
        return $this->notificationChannels()->where('is_active', true);
    }

    /**
     * Get the notification channels by type for this organization.
     */
    public function notificationChannelsByType(string $type)
    {
        return $this->notificationChannels()->where('channel_type', $type);
    }
}
