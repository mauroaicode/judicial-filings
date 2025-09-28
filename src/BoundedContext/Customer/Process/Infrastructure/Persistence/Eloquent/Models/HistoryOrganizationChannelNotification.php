<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models\OrganizationNotificationChannel;

class HistoryOrganizationChannelNotification extends Model
{
    protected $table = 'history_organizations_channels_notifications';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'organization_notification_channel_id',
        'notifiable_id',
        'notifiable_type',
        'notification_type',
        'is_notified',
        'notified_at'
    ];

    protected $casts = [
        'is_notified' => 'boolean',
        'notified_at' => 'datetime'
    ];

    /**
     * Get the parent notifiable model
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the organization notification channel
     */
    public function organizationNotificationChannel(): BelongsTo
    {
        return $this->belongsTo(OrganizationNotificationChannel::class, 'organization_notification_channel_id');
    }

    /**
     * Scope to filter by notification type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('notification_type', $type);
    }

    /**
     * Scope to filter by notifiable type
     */
    public function scopeByNotifiableType($query, string $notifiableType)
    {
        return $query->where('notifiable_type', $notifiableType);
    }

    /**
     * Scope to filter by organization notification channel
     */
    public function scopeByChannel($query, string $channelId)
    {
        return $query->where('organization_notification_channel_id', $channelId);
    }

    /**
     * Scope to filter by notifiable
     */
    public function scopeByNotifiable($query, string $notifiableType, string $notifiableId)
    {
        return $query->where('notifiable_type', $notifiableType)
                    ->where('notifiable_id', $notifiableId);
    }

    /**
     * Scope to filter by notification status
     */
    public function scopeByNotificationStatus($query, bool $isNotified)
    {
        return $query->where('is_notified', $isNotified);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
