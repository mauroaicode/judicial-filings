<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

class NotificationDigest extends Model
{
    use Uuid;

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
