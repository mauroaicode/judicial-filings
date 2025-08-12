<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent\Models;

use Core\Shared\Infrastructure\Traits\Uuid;
use Database\Factories\AppUserFactory;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\{Factories\Factory, Factories\HasFactory, Model, Relations\BelongsToMany};
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

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
 * @property-read Organization $organizations
 * @property-read Organization $ownedOrganizations
 */
class AppUser extends Model implements Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, Uuid;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): Factory|AppUserFactory
    {
        return AppUserFactory::new();
    }

    protected $table = 'app_users';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'slug',
        'email',
        'password',
        'profile_image',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

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
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'app_user_organization')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * Get the organizations where the app user is owner.
     */
    public function ownedOrganizations()
    {
        return $this->organizations()->wherePivot('is_owner', true);
    }
}
