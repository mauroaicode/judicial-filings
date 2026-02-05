<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionResource extends Resource
{
    /**
     * @param  array<int, array{start: int, end: int, text: string}>|null  $alert_highlights
     */
    public function __construct(
        public string $id,
        public int $action_registration_id,
        public string $action_date,
        public string $action,
        public ?string $annotation,
        public ?string $start_date,
        public ?string $end_date,
        public string $registration_date,
        public ?array $alert_highlights = null,
    ) {}

    public static function fromModel(ProcessAction $action): self
    {
        $alertHighlights = self::buildAlertHighlights($action);

        return new self(
            id: $action->id,
            action_registration_id: $action->action_registration_id,
            action_date: DateFormatHelper::formatDate($action->action_date),
            action: $action->action,
            annotation: $action->annotation,
            start_date: $action->start_date ? DateFormatHelper::formatDate($action->start_date) : null,
            end_date: $action->end_date ? DateFormatHelper::formatDate($action->end_date) : null,
            registration_date: DateFormatHelper::formatDate($action->registration_date),
            alert_highlights: $alertHighlights,
        );
    }

    /**
     * @return array<int, array{start: int, end: int, text: string}>|null
     */
    private static function buildAlertHighlights(ProcessAction $action): ?array
    {
        $highlights = $action->relationLoaded('alertHighlights')
            ? $action->alertHighlights->sortBy('start')->values()
            : $action->alertHighlights()->orderedByStart()->get();

        if ($highlights->isEmpty()) {
            return null;
        }

        return $highlights->map(fn ($h): array => [
            'start' => $h->start,
            'end' => $h->end,
            'text' => $h->detected_text,
        ])->values()->all();
    }
}
