<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Database\Factories\ProcessImportBatchFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\QueryBuilders\ProcessImportBatchQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\User\Models\User;

/**
 * @property-read string $id
 * @property-read string|null $organization_id Null for cross-organization imports (e.g. actuaciones-only import)
 * @property-read string|null $requested_by
 * @property-read string $file_name
 * @property-read bool $is_private_import True for synchronous private Excel import (outside judicial queue jobs)
 * @property-read int $excel_total_count Total valid radicados in the uploaded Excel file
 * @property-read int $total_count Radicados actually enqueued (after filtering already-registered for this org)
 * @property-read array<int, string>|null $enqueued_process_numbers
 * @property-read int $success_count Judicial instances registered (may exceed total_count for double-instance radicados)
 * @property-read int $failed_count
 * @property-read int $multiple_instances_count Radicados with more than one judicial instance
 * @property-read string $status
 * @property-read array<int, array{process_number: string, reason: string}> $errors
 * @property-read string|null $laravel_batch_id
 * @property-read Carbon|null $completed_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class ProcessImportBatch extends Model
{
    /** @use HasFactory<ProcessImportBatchFactory> */
    use HasFactory;

    use Uuid;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'process_import_batches';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'organization_id',
        'requested_by',
        'file_name',
        'is_private_import',
        'excel_total_count',
        'total_count',
        'enqueued_process_numbers',
        'success_count',
        'failed_count',
        'multiple_instances_count',
        'status',
        'errors',
        'laravel_batch_id',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'errors' => 'array',
        'completed_at' => 'datetime',
        'excel_total_count' => 'integer',
        'total_count' => 'integer',
        'enqueued_process_numbers' => 'array',
        'success_count' => 'integer',
        'failed_count' => 'integer',
        'multiple_instances_count' => 'integer',
        'is_private_import' => 'boolean',
    ];

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProcessImportBatchFactory
    {
        return ProcessImportBatchFactory::new();
    }

    public function newEloquentBuilder($query): ProcessImportBatchQueryBuilder
    {
        return new ProcessImportBatchQueryBuilder($query);
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

    /**
     * @return Attribute<int, array{process_number: string, reason: string}>
     */
    protected function errors(): Attribute
    {
        return Attribute::make(get: function ($value): array {
            $decoded = is_string($value) ? json_decode($value, true) : $value;

            return is_array($decoded) ? $decoded : [];
        });
    }
}
