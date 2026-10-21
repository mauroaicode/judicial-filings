<?php

declare(strict_types=1);

namespace Src\Domain\AppUser\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property string $app_user_id
 * @property string|null $organization_id
 * @property Carbon $activity_date
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property int $login_count
 * @property string|null $ip_address
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read AppUser|null $appUser
 * @property-read Organization|null $organization
 */
class AppUserDailyActivity extends Model
{
    use Uuid;

    protected $table = 'app_user_daily_activities';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'app_user_id',
        'organization_id',
        'activity_date',
        'first_seen_at',
        'last_seen_at',
        'login_count',
        'ip_address',
        'country',
        'region',
        'city',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'login_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AppUser, $this>
     */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(AppUser::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
