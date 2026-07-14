<?php

declare(strict_types=1);

namespace Src\Domain\Task\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\QueryBuilders\TaskQueryBuilder;

/**
 * @property-read string $id
 * @property-read string $title
 * @property-read string $description
 * @property-read TaskType $type
 * @property-read \Illuminate\Support\Carbon|null $due_date
 * @property-read int|null $reminder_days
 * @property-read TaskStatus $status
 * @property-read string|null $last_notified_urgency_level
 * @property-read \Illuminate\Support\Carbon|null $last_due_reminder_sent_on
 * @property-read bool $is_admin
 * @property-read string|null $process_id
 * @property-read string $organization_id
 * @property-read \Illuminate\Support\Carbon|null $created_at
 * @property-read \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Support\Carbon|null $deleted_at
 * @property-read Organization|null $organization
 * @property-read Process|null $process
 *
 * @method static TaskQueryBuilder query()
 * @method TaskQueryBuilder whereOrganization(string $organizationId)
 * @method TaskQueryBuilder whereAdmin(bool $isAdmin = true)
 * @method TaskQueryBuilder whereAppUser()
 * @method TaskQueryBuilder whereProcess(string $processId)
 * @method TaskQueryBuilder whereStatus(TaskStatus|string $status)
 * @method TaskQueryBuilder whereType(\Src\Domain\Task\Enums\TaskType|string $type)
 * @method TaskQueryBuilder excludingCompleted()
 * @method TaskQueryBuilder orderedByCreatedAt()
 */
class Task extends Model
{
    use HasFactory;
    use SoftDeletes;
    use Uuid;

    protected $table = 'tasks';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'due_date',
        'reminder_days',
        'status',
        'last_notified_urgency_level',
        'last_due_reminder_sent_on',
        'is_admin',
        'process_id',
        'organization_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'due_date' => 'datetime',
            'reminder_days' => 'integer',
            'status' => TaskStatus::class,
            'last_due_reminder_sent_on' => 'date',
            'is_admin' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): TaskFactory
    {
        return TaskFactory::new();
    }

    public function newEloquentBuilder($query): TaskQueryBuilder
    {
        return new TaskQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }
}
