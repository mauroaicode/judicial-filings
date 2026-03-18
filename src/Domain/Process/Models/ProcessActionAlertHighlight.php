<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Src\Domain\Process\QueryBuilders\ProcessActionAlertHighlightQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;

/**
 * Posición de una palabra clave detectada en el texto (anotación/actuación).
 * Guarda la palabra tal como viene (puede venir mal escrita). La relación
 * para filtrar (actuaciones por tipo) es la pivot process_action_alert_action_keyword.
 *
 * @property-read string $id
 * @property-read string $process_action_id
 * @property-read int $start
 * @property-read int $end
 * @property-read string $detected_text
 * @property-read string $source  'annotation' | 'action' | 'both'
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
        'organization_id',
        'start',
        'end',
        'detected_text',
        'source',
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
