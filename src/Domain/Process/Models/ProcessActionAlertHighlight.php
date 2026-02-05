<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Src\Domain\Process\QueryBuilders\ProcessActionAlertHighlightQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $process_action_id
 * @property-read int $start
 * @property-read int $end
 * @property-read string $detected_text
 * @property-read ProcessAction $processAction
 *
 * @method static ProcessActionAlertHighlightQueryBuilder query()
 * @method ProcessActionAlertHighlightQueryBuilder whereProcessAction(string $processActionId)
 * @method ProcessActionAlertHighlightQueryBuilder orderedByStart()
 */
class ProcessActionAlertHighlight extends Model
{
    use Uuid;

    protected $table = 'process_action_alert_highlights';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'process_action_id',
        'start',
        'end',
        'detected_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start' => 'integer',
            'end' => 'integer',
        ];
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder(mixed $query): ProcessActionAlertHighlightQueryBuilder
    {
        return new ProcessActionAlertHighlightQueryBuilder($query);
    }

    /**
     * @return BelongsTo<ProcessAction, $this>
     */
    public function processAction(): BelongsTo
    {
        return $this->belongsTo(ProcessAction::class, 'process_action_id');
    }
}
