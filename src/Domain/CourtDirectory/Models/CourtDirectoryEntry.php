<?php

declare(strict_types=1);

namespace Src\Domain\CourtDirectory\Models;

use Database\Factories\CourtDirectoryEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property string $email
 * @property string $name
 * @property string|null $corporation
 * @property string|null $specialty
 * @property string|null $department
 * @property string|null $city
 * @property string|null $account_type
 * @property string|null $desk_code
 * @property bool $is_active
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $missing_at
 * @property string|null $last_sync_run_id
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 */
class CourtDirectoryEntry extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'court_directory_entries';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'name',
        'corporation',
        'specialty',
        'department',
        'city',
        'account_type',
        'desk_code',
        'is_active',
        'last_seen_at',
        'missing_at',
        'last_sync_run_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'missing_at' => 'datetime',
        ];
    }

    protected static function newFactory(): CourtDirectoryEntryFactory
    {
        return CourtDirectoryEntryFactory::new();
    }

    /**
     * @return BelongsTo<CourtDirectorySyncRun, $this>
     */
    public function lastSyncRun(): BelongsTo
    {
        return $this->belongsTo(CourtDirectorySyncRun::class, 'last_sync_run_id');
    }
}
