<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\{
    Model,
    Factories\HasFactory,
    Relations\BelongsToMany
};
use Database\Factories\AppUserFactory;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Core\Shared\Infrastructure\Traits\Uuid;

class AppUser extends Model implements Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, Uuid;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
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
