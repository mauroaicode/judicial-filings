<?php

declare(strict_types=1);

namespace Src\Domain\Process\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

/**
 * @extends Builder<ProcessActionAlertHighlight>
 */
class ProcessActionAlertHighlightQueryBuilder extends Builder
{
    public function whereProcessAction(string $processActionId): self
    {
        return $this->where('process_action_id', $processActionId);
    }

    public function orderedByStart(): self
    {
        return $this->orderBy('start');
    }
}
