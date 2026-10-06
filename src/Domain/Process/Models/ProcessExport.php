<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Database\Factories\ProcessExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessExportStatus;
use Src\Domain\Process\QueryBuilders\ProcessExportQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\User\Models\User;

/**
 * @property-read string $id
 * @property-read string $organization_id
 * @property-read string|null $requested_by
 * @property-read ProcessExportStatus $status
 * @property-read array<string, mixed>|null $filters
 * @property-read int $row_count
 * @property-read int $action_row_count
 * @property-read string|null $disk
 * @property-read string|null $file_path
 * @property-read string|null $file_name
 * @property-read string|null $error_message
 * @property-read Carbon|null $started_at
 * @property-read Carbon|null $completed_at
 * @property-read Carbon|null $expires_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class ProcessExport extends Model
{
    /** @use HasFactory<ProcessExportFactory> */
    use HasFactory;

    use Uuid;

    protected $table = 'process_exports';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'requested_by',
        'status',
        'filters',
        'row_count',
        'action_row_count',
        'disk',
        'file_path',
        'file_name',
        'error_message',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProcessExportStatus::class,
            'filters' => 'array',
            'row_count' => 'integer',
            'action_row_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ProcessExportFactory
    {
        return ProcessExportFactory::new();
    }

    public function newEloquentBuilder($query): ProcessExportQueryBuilder
    {
        return new ProcessExportQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isDownloadable(): bool
    {
        return $this->status === ProcessExportStatus::Completed
            && filled($this->file_path)
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
