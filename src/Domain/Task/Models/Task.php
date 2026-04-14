<?php

declare(strict_types=1);

namespace Src\Domain\Task\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\Task\QueryBuilders\TaskQueryBuilder;

/**
 * @method static TaskQueryBuilder query()
 * @method TaskQueryBuilder whereOrganization(string $organizationId)
 * @method TaskQueryBuilder whereAdmin(bool $isAdmin = true)
 * @method TaskQueryBuilder whereAppUser()
 * @method TaskQueryBuilder whereProcess(string $processId)
 * @method TaskQueryBuilder orderedByCreatedAt()
 */
class Task extends Model
{
    use HasFactory;
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
            'is_admin' => 'boolean',
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
