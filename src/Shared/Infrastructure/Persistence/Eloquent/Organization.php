<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Core\Shared\Infrastructure\Traits\Uuid;

class Organization extends Model
{
    use HasFactory, Uuid;

    protected $table = 'organizations';

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'identification',
        'address',
        'phone',
        'email',
        'contact_person',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'type' => 'string',
    ];

    /**
     * Get the app users for the organization.
     */
    public function appUsers(): BelongsToMany
    {
        return $this->belongsToMany(AppUser::class, 'app_user_organization')
            ->withPivot('is_owner')
            ->withTimestamps();
    }

    /**
     * Get the owners of the organization.
     */
    public function owners()
    {
        return $this->appUsers()->wherePivot('is_owner', true);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\OrganizationFactory::new();
    }
}
