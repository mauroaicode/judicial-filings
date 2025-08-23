<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models;

use Carbon\Carbon;
use Core\Shared\Domain\Enums\NotificationChannelType;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;
use Core\Shared\Infrastructure\Traits\Uuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Organization Notification Channel Model
 *
 * Represents a notification channel for an organization (email, whatsapp, sms, internal).
 *
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read NotificationChannelType $channel_type
 * @property-read string $channel_value
 * @property-read bool $is_active
 * @property-read int $priority
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Organization $organization
 */
class OrganizationNotificationChannel extends Model
{
    use Uuid;

    protected $table = 'organization_notification_channels';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'organization_id',
        'channel_type',
        'channel_value',
        'is_active',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'channel_type' => NotificationChannelType::class,
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the organization that owns this notification channel.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Scope to get only active channels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get channels by type.
     */
    public function scopeByType($query, NotificationChannelType $type)
    {
        return $query->where('channel_type', $type);
    }

    /**
     * Scope to get channels by priority.
     */
    public function scopeByPriority($query, int $priority)
    {
        return $query->where('priority', $priority);
    }
}
