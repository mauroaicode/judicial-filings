<?php

declare(strict_types=1);

namespace Src\Domain\Process\Models;

use Database\Factories\ProcessActionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Src\Domain\Process\QueryBuilders\ProcessActionQueryBuilder;
use Src\Domain\Shared\Traits\Uuid;

/**
 * @property-read string $id
 * @property-read string $process_id
 * @property-read int $action_registration_id
 * @property-read Carbon $action_date
 * @property-read string $action
 * @property-read string|null $annotation
 * @property-read Carbon|null $start_date
 * @property-read Carbon|null $end_date
 * @property-read Carbon $registration_date
 * @property-read Carbon $created_at
 * @property-read Carbon $updated_at
 * @property-read Process $process
 * @property-read Collection<int, ProcessActionAlertHighlight> $alertHighlights
 * @property-read Collection<int, AlertActionKeyword> $alertActionKeywords
 *
 * @method static ProcessActionQueryBuilder query()
 * @method ProcessActionQueryBuilder whereProcess(string $processId)
 * @method ProcessActionQueryBuilder whereActionRegistrationId(int $actionRegistrationId)
 * @method ProcessActionQueryBuilder whereProcessAndRegistrationId(string $processId, int $actionRegistrationId)
 * @method ProcessActionQueryBuilder orderedByActionDate()
 * @method ProcessActionQueryBuilder orderedByRegistrationDate()
 */
class ProcessAction extends Model
{
    use HasFactory;
    use Uuid;

    protected $table = 'process_actions';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'process_id',
        'action_registration_id',
        'action_date',
        'action',
        'annotation',
        'start_date',
        'end_date',
        'registration_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'registration_date' => 'date',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): ProcessActionFactory
    {
        return ProcessActionFactory::new();
    }

    /**
     * @param  Builder  $query
     */
    public function newEloquentBuilder(mixed $query): ProcessActionQueryBuilder
    {
        return new ProcessActionQueryBuilder($query);
    }

    /**
     * Get the process that owns the action.
     *
     * @return BelongsTo<Process, $this>
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Get the alert highlights (position of keyword in text) for this action.
     *
     * @return HasMany<ProcessActionAlertHighlight, $this>
     */
    public function alertHighlights(): HasMany
    {
        return $this->hasMany(ProcessActionAlertHighlight::class, 'process_action_id');
    }

    /**
     * Alert keyword types linked to this action (direct relation for filtering e.g. "all actions with Apelación").
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Src\Domain\Process\Models\AlertActionKeyword, $this>
     */
    public function alertActionKeywords(): BelongsToMany
    {
        return $this->belongsToMany(
            AlertActionKeyword::class,
            'process_action_alert_action_keyword',
            'process_action_id',
            'alert_action_keyword_id'
        );
    }
}
