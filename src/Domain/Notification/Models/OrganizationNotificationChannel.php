<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Src\Domain\Notification\Enums\NotificationChannelType;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string $channel_type
 * @property-read string $channel_value
 * @property-read bool $is_active
 * @property Carbon|null $validated_at
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
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'channel_type',
        'channel_value',
        'is_active',
        'validated_at',
        'priority',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'validated_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $channel): void {
            if (! array_key_exists('validated_at', $channel->getAttributes())) {
                $channel->validated_at = now();
            }
        });

        static::created(function (self $channel): void {
            if ($channel->channel_type !== NotificationChannelType::Email->value || $channel->validated_at === null) {
                return;
            }

            ValidatedNotificationEmail::remember((string) $channel->channel_value, $channel->validated_at);
        });
    }

    /**
     * Destinations that may receive notifications: switched on and confirmed.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    protected function scopeDeliverable(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotNull('validated_at');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<HistoryOrganizationChannelNotification, $this>
     */
    public function historyRecords(): HasMany
    {
        return $this->hasMany(HistoryOrganizationChannelNotification::class, 'organization_notification_channel_id');
    }
}
