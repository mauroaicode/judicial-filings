<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Persistence\Eloquent\Models;

use Database\Factories\ProcessFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Core\Shared\Infrastructure\Traits\Uuid;
use Illuminate\Support\Carbon;

/**
 * @property-read string $id
 * @property-read int $process_id
 * @property-read string $process_number
 * @property-read string $court
 * @property-read string $department
 * @property-read string $process_type
 * @property-read string $process_class
 * @property-read string|null $subclass_process
 * @property-read string|null $litigants
 * @property-read Carbon $process_date
 * @property-read Carbon|null $last_activity_date
 * @property-read string|null $location
 * @property-read string|null $filing_content
 * @property-read bool $is_private
 * @property-read bool $has_multiple_instances
 * @property-read Carbon|null $last_api_update
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read ProcessAction $actions
 * @property-read ProcessSubject $subjects
 * @property-read Organization $organizations
 */
class Process extends Model
{
    use HasFactory, Uuid;

    protected $table = 'processes';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'process_id',
        'process_number',
        'court',
        'department',
        'process_type',
        'process_class',
        'subclass_process',
        'litigants',
        'process_date',
        'last_activity_date',
        'location',
        'filing_content',
        'is_private',
        'has_multiple_instances',
        'last_api_update',
    ];

    protected $casts = [
        'process_number' => 'decimal:0',
        'process_date' => 'date',
        'last_activity_date' => 'date',
        'is_private' => 'boolean',
        'has_multiple_instances' => 'boolean',
        'last_api_update' => 'datetime',
    ];

    /**
     * Get the actions for the process.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ProcessAction::class);
    }

    /**
     * Get the subjects for the process.
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(ProcessSubject::class);
    }

    /**
     * Get the organizations that have access to this process.
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_processes')
            ->withTimestamps();
    }

    protected static function newFactory(): ProcessFactory
    {
        return ProcessFactory::new();
    }
}
