<?php

declare(strict_types=1);

namespace Src\Domain\CourtDirectory\Models;

use Database\Factories\CourtDirectorySyncRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 * @property CourtDirectorySyncTrigger $trigger
 * @property CourtDirectorySyncStatus $status
 * @property int $rows_fetched
 * @property int $rows_upserted
 * @property int $rows_marked_inactive
 * @property string|null $error
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class CourtDirectorySyncRun extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'court_directory_sync_runs';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'started_at',
        'finished_at',
        'trigger',
        'status',
        'rows_fetched',
        'rows_upserted',
        'rows_marked_inactive',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'trigger' => CourtDirectorySyncTrigger::class,
            'status' => CourtDirectorySyncStatus::class,
            'rows_fetched' => 'integer',
            'rows_upserted' => 'integer',
            'rows_marked_inactive' => 'integer',
        ];
    }

    protected static function newFactory(): CourtDirectorySyncRunFactory
    {
        return CourtDirectorySyncRunFactory::new();
    }

    /**
     * @return HasMany<CourtDirectoryEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(CourtDirectoryEntry::class, 'last_sync_run_id');
    }
}
