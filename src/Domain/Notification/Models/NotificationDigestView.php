<?php

declare(strict_types=1);

namespace Src\Domain\Notification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Enums\NotificationDigestOpenedVia;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property string $id
 * @property string $notification_digest_id
 * @property string $organization_id
 * @property string $app_user_id
 * @property NotificationDigestOpenedVia $opened_via
 * @property Carbon $viewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class NotificationDigestView extends Model
{
    use Uuid;

    protected $table = 'notification_digest_views';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'notification_digest_id',
        'organization_id',
        'app_user_id',
        'opened_via',
        'viewed_at',
    ];

    protected function casts(): array
    {
        return [
            'opened_via' => NotificationDigestOpenedVia::class,
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NotificationDigest, $this>
     */
    public function digest(): BelongsTo
    {
        return $this->belongsTo(NotificationDigest::class, 'notification_digest_id');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * @return BelongsTo<AppUser, $this>
     */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class, 'app_user_id');
    }
}
