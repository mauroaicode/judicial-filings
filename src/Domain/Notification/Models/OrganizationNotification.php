<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;

/**
 * @property-read string $organization_id
 * @property-read string $notifiable_id
 * @property-read string $notifiable_type
 * @property-read string $notification_type
 * @property-read bool $is_viewed
 * @property-read bool $is_notified
 * @property-read Carbon|null $viewed_at
 * @property-read Carbon|null $notified_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Organization $organization
 * @property-read \Illuminate\Database\Eloquent\Model|null $notifiable
 */
class OrganizationNotification extends Model
{
    protected $table = 'organization_notifications';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Composite primary key columns (in order).
     *
     * @var list<string>
     */
    /** @phpstan-ignore-next-line property.phpDocType (composite key override; parent expects string) */
    protected $primaryKey = ['organization_id', 'notifiable_id', 'notifiable_type', 'notification_type'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'notifiable_id',
        'notifiable_type',
        'notification_type',
        'is_viewed',
        'is_notified',
        'viewed_at',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_viewed' => 'boolean',
            'is_notified' => 'boolean',
            'viewed_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /**
     * Get the first key name (for compatibility with Eloquent methods that expect a single key name).
     */
    public function getKeyName(): string
    {
        return $this->primaryKey[0];
    }

    /**
     * Get the value of the primary key.
     */
    public function getKey(): ?string
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Set the keys for a save/update query (composite key).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function setKeysForSaveQuery($query): Builder
    {
        /** @var list<string> $primaryKeyColumns */
        $primaryKeyColumns = $this->primaryKey;

        foreach ($primaryKeyColumns as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }

        return $query;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return MorphTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo('notifiable');
    }
}
