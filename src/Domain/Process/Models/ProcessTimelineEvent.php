<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Database\Factories\ProcessTimelineEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\QueryBuilders\ProcessTimelineEventQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $process_id
 * @property-read string $process_number
 * @property-read string|null $organization_id
 * @property-read ProcessTimelineEventType $event_type
 * @property-read Carbon $occurred_at
 * @property-read Carbon $recorded_at
 * @property-read ProcessTimelineEventSource $source
 * @property-read string|null $subject_type
 * @property-read string|null $subject_id
 * @property-read string|null $actor_type
 * @property-read string|null $actor_id
 * @property-read array<string, mixed> $payload
 * @property-read string $idempotency_key
 * @property-read bool $is_backfilled
 * @property-read bool $occurred_at_is_estimated
 * @property-read Carbon $created_at
 * @property-read Process $process
 * @property-read Organization|null $organization
 *
 * @method static ProcessTimelineEventQueryBuilder query()
 */
class ProcessTimelineEvent extends Model
{
    use HasFactory;
    use Uuid;

    public const UPDATED_AT = null;

    protected $table = 'process_timeline_events';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'process_id',
        'process_number',
        'organization_id',
        'event_type',
        'occurred_at',
        'recorded_at',
        'source',
        'subject_type',
        'subject_id',
        'actor_type',
        'actor_id',
        'payload',
        'idempotency_key',
        'is_backfilled',
        'occurred_at_is_estimated',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ProcessTimelineEventType::class,
            'occurred_at' => 'datetime',
            'recorded_at' => 'datetime',
            'source' => ProcessTimelineEventSource::class,
            'payload' => 'array',
            'is_backfilled' => 'boolean',
            'occurred_at_is_estimated' => 'boolean',
        ];
    }

    protected static function newFactory(): ProcessTimelineEventFactory
    {
        return ProcessTimelineEventFactory::new();
    }

    public function newEloquentBuilder(mixed $query): ProcessTimelineEventQueryBuilder
    {
        /** @var Builder $query */
        return new ProcessTimelineEventQueryBuilder($query);
    }

    /**
     * @return BelongsTo<Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
