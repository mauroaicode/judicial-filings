<?php

declare(strict_types=1);

namespace Src\Domain\AppUser\Models;

use Database\Factories\AppUserFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\AppUser\QueryBuilders\AppUserQueryBuilder;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $last_name
 * @property-read string $slug
 * @property-read string $email
 * @property-read string $password
 * @property-read string|null $profile_image
 * @property-read Carbon|null $email_verified_at
 * @property-read string|null $remember_token
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class AppUser extends Model implements Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use Uuid;

    protected $table = 'app_users';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'slug',
        'email',
        'identification',
        'password',
        'must_change_password',
        'profile_image',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): AppUserFactory
    {
        return AppUserFactory::new();
    }

    /**
     * Get the name of the unique identifier for the user.
     */
    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): mixed
    {
        return $this->getAttribute($this->getAuthIdentifierName());
    }

    /**
     * Get the password for the user.
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Get the column name for the password.
     */
    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    /**
     * Get the token value for the "remember me" session.
     */
    public function getRememberToken(): string
    {
        return $this->getAttribute($this->getRememberTokenName());
    }

    /**
     * Set the token value for the "remember me" session.
     */
    public function setRememberToken($value): void
    {
        $this->setAttribute($this->getRememberTokenName(), $value);
    }

    /**
     * Get the column name for the "remember me" token.
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Get the organizations that the app user belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'app_user_organization',
            'app_user_id',
            'organization_id'
        )->withPivot('is_owner')->withTimestamps();
    }

    /**
     * Get the organizations where the app user is owner.
     *
     * @return BelongsToMany<Organization>
     */
    public function ownedOrganizations(): BelongsToMany
    {
        return $this->organizations()->wherePivot('is_owner', true);
    }

    /**
     * Get the guard name for the model.
     * Used by Spatie Permission to determine which guard to use.
     */
    public function getGuardName(): string
    {
        return 'app_user';
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return AppUserQueryBuilder<AppUser>
     */
    public function newEloquentBuilder($query): AppUserQueryBuilder
    {
        return new AppUserQueryBuilder($query);
    }
}
