<?php

namespace Core\BoundedContext\Admin\User\Infrastructure\Persistence\Eloquent;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\{
    Model,
    Factories\HasFactory
};
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class UserModel extends Model
{
    use HasFactory, Notifiable, HasRoles;
    protected $table = 'users';

    protected $keyType = 'string';
    public $incrementing = false;

    const ACTIVE = 'active';
    const INACTIVE = 'inactive';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'last_name',
        'phone',
        'address',
        'slug',
        'picture',
        'email',
        'password',
    ];

    protected $guarded = ['id'];

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

    protected $appends = ['role_names'];

    public function getRoleNamesAttribute() {
        return $this->roles->pluck('name');
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }
}
