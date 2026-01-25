<?php

declare(strict_types=1);

namespace Src\Domain\User\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\QueryBuilders\UserQueryBuilder;

/**
 * @property-read string $id
 * @property-read string $name
 * @property-read string $last_name
 * @property-read string $phone
 * @property-read string $address
 * @property-read string $slug
 * @property-read string $email
 * @property-read string $password
 * @property-read UserStatus $state
 * @property-read string|null $remember_token
 * @property-read Carbon|null $email_verified_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static UserQueryBuilder query()
 * @method UserQueryBuilder active()
 * @method UserQueryBuilder inactive()
 * @method UserQueryBuilder whereEmail(string $email)
 * @method UserQueryBuilder whereSlug(string $slug)
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use Uuid;

    protected $keyType = 'string';

    public $incrementing = false;

    const ACTIVE = 'active';

    const INACTIVE = 'inactive';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'phone',
        'address',
        'slug',
        'email',
        'password',
        'state',
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
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder(mixed $query): UserQueryBuilder
    {
        return new UserQueryBuilder($query);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the email address that should be used for verification.
     */
    public function getEmailForVerification(): string
    {
        return $this->email;
    }

    /**
     * Determine if the user has verified their email address.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    /**
     * Mark the given user's email as verified.
     */
    public function markEmailAsVerified(): bool
    {
        return $this->update([
            'email_verified_at' => now(),
        ]);
    }
}
