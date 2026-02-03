<?php

declare(strict_types=1);

namespace Src\Domain\Organization\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Models\OrganizationNotificationChannel;
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
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
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
        )->withPivot(['interest_date', 'is_active'])->withTimestamps();
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
}
