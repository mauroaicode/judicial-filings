<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Process\QueryBuilders\UnassignedProcessActionQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;
use Src\Domain\User\Models\User;

/**
 * Actuación imported from manual Excel before a Process existed for the radicado.
 *
 * @property-read string $id
 * @property-read string $process_number
 * @property-read string|null $court
 * @property-read string|null $process_class
 * @property-read string|null $plaintiffs_raw
 * @property-read string|null $defendants_raw
 * @property-read string $action
 * @property-read string|null $annotation
 * @property-read Carbon|null $start_date
 * @property-read Carbon|null $end_date
 * @property-read Carbon|null $registration_date
 * @property-read string $dedupe_hash
 * @property-read string|null $import_batch_id
 * @property-read string|null $imported_by
 * @property-read string|null $assigned_process_id
 * @property-read Carbon|null $assigned_at
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 *
 * @method static UnassignedProcessActionQueryBuilder query()
 */
class UnassignedProcessAction extends Model
{
    use Uuid;

    protected $table = 'unassigned_process_actions';

    protected $keyType = 'string';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'process_number',
        'court',
        'process_class',
        'plaintiffs_raw',
        'defendants_raw',
        'action',
        'annotation',
        'start_date',
        'end_date',
        'registration_date',
        'dedupe_hash',
        'import_batch_id',
        'imported_by',
        'assigned_process_id',
        'assigned_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'registration_date' => 'date',
        'assigned_at' => 'datetime',
    ];

    public function newEloquentBuilder($query): UnassignedProcessActionQueryBuilder
    {
        return new UnassignedProcessActionQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Process, $this>
     */
    public function assignedProcess(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'assigned_process_id');
    }

    /**
     * @return BelongsTo<ProcessImportBatch, $this>
     */
    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ProcessImportBatch::class, 'import_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    /**
     * Stable dedupe key for a radicado + actuación payload.
     */
    public static function makeDedupeHash(
        string $processNumber,
        string $action,
        ?string $annotation,
        ?string $registrationDate,
        ?string $court = null,
    ): string {
        $payload = implode('|', [
            mb_strtolower(trim($processNumber)),
            mb_strtolower(trim($court ?? '')),
            mb_strtolower(trim($action)),
            mb_strtolower(trim((string) $annotation)),
            (string) $registrationDate,
        ]);

        return hash('sha256', $payload);
    }
}
