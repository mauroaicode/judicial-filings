<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $organization_notification_channel_id
 * @property-read string $notifiable_id
 * @property-read string $notifiable_type
 * @property-read string $notification_type
 * @property-read bool $is_notified
 * @property-read Carbon|null $notified_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read OrganizationNotificationChannel $organizationNotificationChannel
 * @property-read \Illuminate\Database\Eloquent\Model $notifiable
 */
class HistoryOrganizationChannelNotification extends Model
{
    use Uuid;

    protected $table = 'history_organizations_channels_notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_notification_channel_id',
        'notifiable_id',
        'notifiable_type',
        'notification_type',
        'is_notified',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_notified' => 'boolean',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Notification\Models\OrganizationNotificationChannel, $this>
     */
    public function organizationNotificationChannel(): BelongsTo
    {
        return $this->belongsTo(OrganizationNotificationChannel::class, 'organization_notification_channel_id');
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo('notifiable');
    }
}
