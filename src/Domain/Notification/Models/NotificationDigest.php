<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Database\Factories\NotificationDigestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Src\Domain\Notification\QueryBuilders\NotificationDigestQueryBuilder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property string $id
 * @property string $organization_id
 * @property array $data
 * @property Carbon|null $email_sent_at
 * @property Carbon|null $whatsapp_sent_at
 * @property Carbon|null $sms_sent_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 *
 * @method static NotificationDigestQueryBuilder query()
 */
class NotificationDigest extends Model
{
    use HasFactory;
    use Uuid;

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  Builder  $query
     */
    public function newEloquentBuilder($query): NotificationDigestQueryBuilder
    {
        return new NotificationDigestQueryBuilder($query);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): NotificationDigestFactory
    {
        return NotificationDigestFactory::new();
    }

    protected $table = 'notification_digests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'organization_id',
        'data',
        'email_sent_at',
        'whatsapp_sent_at',
        'sms_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'json',
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
            'sms_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return HasMany<OrganizationNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(OrganizationNotification::class, 'notification_digest_id');
    }
}
