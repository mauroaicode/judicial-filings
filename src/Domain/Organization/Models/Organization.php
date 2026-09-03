<?php

declare(strict_types=1);

namespace Src\Domain\Organization\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
use Src\Domain\Organization\QueryBuilders\OrganizationQueryBuilder;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $slug
 * @property-read string $type
 * @property-read string|null $identification
 * @property-read string|null $address
 * @property-read string|null $phone
 * @property-read string|null $email
 * @property-read string|null $contact_person
 * @property-read bool $is_active
 * @property-read bool $is_ai_enabled
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read OrganizationSetting|null $settings
 *
 * @method static OrganizationQueryBuilder query()
 * @method OrganizationQueryBuilder withRelations()
 * @method OrganizationQueryBuilder orderedByCreatedAt()
 * @method OrganizationQueryBuilder whereActive()
 * @method OrganizationQueryBuilder whereInactive()
 * @method OrganizationQueryBuilder whereNatural()
 * @method OrganizationQueryBuilder whereJuridical()
 * @method OrganizationQueryBuilder filters(\Src\Application\Admin\Organization\Data\OrganizationFilterData $data)
 */
class Organization extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'organizations';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'identification',
        'address',
        'phone',
        'email',
        'contact_person',
        'is_active',
        'is_ai_enabled',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => 'string',
            'is_active' => 'boolean',
            'is_ai_enabled' => 'boolean',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    /**
     * Get the app users for the organization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\AppUser\Models\AppUser, $this>
     */
    public function appUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            AppUser::class,
            'app_user_organization',
            'organization_id',
            'app_user_id'
        )->withPivot('is_owner')->withTimestamps();
    }

    /**
     * Get the owners of the organization.
     *
     * @return BelongsToMany<AppUser>
     */
    public function owners(): BelongsToMany
    {
        return $this->appUsers()->wherePivot('is_owner', true);
    }

    /**
     * Get the processes that this organization is following.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Process\Models\Process, $this>
     */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(
            Process::class,
            'organization_processes',
            'organization_id',
            'process_id'
        )
            ->using(OrganizationProcess::class)
            ->withPivot(['interest_date', 'is_active', 'status', 'lawyer_role', 'inactivity_alert_level', 'deleted_at', 'deleted_by'])
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * Product/commercial settings for this organization.
     *
     * @return HasOne<OrganizationSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    /**
     * Get the notification channels for the organization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Src\Domain\Notification\Models\OrganizationNotificationChannel, $this>
     */
    public function notificationChannels(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrganizationNotificationChannel::class, 'organization_id');
    }

    /**
     * Get the keywords for the organization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Src\Domain\Keyword\Models\Keyword, $this>
     */
    public function keywords(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Src\Domain\Keyword\Models\Keyword::class, 'organization_id');
    }

    /**
     * Get the notifications for the organization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Src\Domain\Notification\Models\OrganizationNotification, $this>
     */
    public function notifications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Src\Domain\Notification\Models\OrganizationNotification::class, 'organization_id');
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    public function newEloquentBuilder($query): OrganizationQueryBuilder
    {
        return new OrganizationQueryBuilder($query);
    }

    /**
     * Get the tasks for the organization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\Src\Domain\Task\Models\Task, $this>
     */
    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\Src\Domain\Task\Models\Task::class, 'organization_id');
    }
}
