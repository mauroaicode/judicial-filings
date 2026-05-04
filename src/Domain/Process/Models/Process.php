<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Database\Factories\ProcessFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Src\Application\Shared\Data\ProcessFilterData;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\QueryBuilders\ProcessQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\Task\Models\Task;

/**
 * @property-read string $id
 * @property-read int $process_id
 * @property-read string $process_number
 * @property-read string $court
 * @property-read string|null $speaker
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
 * @property-read string|null $status
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static ProcessQueryBuilder query()
 * @method ProcessQueryBuilder whereProcessNumber(string $processNumber)
 * @method ProcessQueryBuilder whereProcessId(int $processId)
 * @method ProcessQueryBuilder whereOrganization(string $organizationId)
 * @method ProcessQueryBuilder withActions()
 * @method ProcessQueryBuilder withSubjects()
 * @method ProcessQueryBuilder withRelations()
 * @method ProcessQueryBuilder orderedByCreatedAt()
 * @method ProcessQueryBuilder orderedByProcessDate()
 * @method ProcessQueryBuilder orderedByLastApiUpdate()
 * @method ProcessQueryBuilder orderedByLastActivityDate()
 * @method ProcessQueryBuilder filters(ProcessFilterData $data)
 * @method ProcessQueryBuilder whereJudiciallyActive()
 * @method ProcessQueryBuilder whereJudiciallyInactive()
 * @method ProcessQueryBuilder forJudicialDailySync(?string $radicadoFilter = null)
 */
class Process extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'processes';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'process_id',
        'process_number',
        'court',
        'speaker',
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
        'status',
        'ai_summary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'process_number' => 'string',
            'process_date' => 'date',
            'last_activity_date' => 'date',
            'is_private' => 'boolean',
            'has_multiple_instances' => 'boolean',
            'last_api_update' => 'datetime',
            'ai_summary' => 'array',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProcessFactory
    {
        return ProcessFactory::new();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder(mixed $query): ProcessQueryBuilder
    {
        return new ProcessQueryBuilder($query);
    }

    /**
     * Get the actions for the process.
     *
     * @return HasMany<ProcessAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ProcessAction::class, 'process_id');
    }

    /**
     * Get the subjects for the process (many-to-many via pivot).
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Process\Models\ProcessSubject, $this>
     */
    public function subjects(): EloquentBelongsToMany
    {
        return $this->belongsToMany(
            ProcessSubject::class,
            'process_process_subject',
            'process_id',
            'process_subject_id'
        );
    }

    /**
     * Get the organizations that have access to this process.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Organization\Models\Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(
            Organization::class,
            'organization_processes',
            'process_id',
            'organization_id'
        )->withPivot(['interest_date', 'is_active', 'lawyer_role', 'inactivity_alert_level'])->withTimestamps();
    }

    /**
     * Get the tasks for the organization.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'process_id');
    }
}
