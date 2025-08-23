<?php

namespace Core\BoundedContext\Customer\Process\Infrastructure\Persistence\Eloquent\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Core\Shared\Infrastructure\Persistence\Eloquent\Models\Organization;

class OrganizationNotification extends Model
{
    protected $table = 'organization_notifications';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'notifiable_id',
        'notifiable_type',
        'notification_type',
        'is_viewed',
        'is_notified',
        'viewed_at',
        'notified_at'
    ];

    protected $casts = [
        'is_viewed' => 'boolean',
        'is_notified' => 'boolean',
        'viewed_at' => 'datetime',
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
     * Get the organization that owns the notification
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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
     * Scope to filter by organization
     */
    public function scopeByOrganization($query, string $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Scope to filter by notifiable
     */
    public function scopeByNotifiable($query, string $notifiableType, string $notifiableId)
    {
        return $query->where('notifiable_type', $notifiableType)
                    ->where('notifiable_id', $notifiableId);
    }

    protected function setKeysForSaveQuery($query): Builder
    {
        $query->where('organization_id', '=', $this->organization_id)
            ->where('notifiable_id', '=', $this->notifiable_id)
            ->where('notifiable_type', '=', $this->notifiable_type);
        return $query;
    }
}
