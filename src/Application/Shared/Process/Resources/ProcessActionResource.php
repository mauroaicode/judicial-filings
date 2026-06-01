<?php

declare(strict_types=1);

namespace Src\Application\Shared\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ProcessAction;

class ProcessActionResource extends Resource
{
    /**
     * @param  array<int, array{start: int, end: int, text: string}>|null  $alert_highlights
     */
    public function __construct(
        public int $index,
        public string $id,
        public int $action_registration_id,
        public int $cons_action,
        public string $action_date,
        public string $action,
        public ?string $annotation,
        public string $term_start_date,
        public string $term_end_date,
        public string $registration_date,
        public ?array $alert_highlights = null,
        public ?string $process_number = null,
        public ?string $alert_level = null,
    ) {}

    public static function fromModel(ProcessAction $action, int $index = 0): self
    {
        $alertHighlights = self::buildAlertHighlights($action);
        $process = $action->relationLoaded('process') ? $action->process : $action->process()->first();

        $alertLevel = null;
        if ($process) {
            $organization = $process->organizations->first();
            if ($organization && isset($organization->pivot->inactivity_alert_level)) {
                $alertLevel = $organization->pivot->inactivity_alert_level;
            }
        }

        return new self(
            index: $index,
            id: $action->id,
            action_registration_id: $action->action_registration_id,
            cons_action: $action->cons_action,
            action_date: DateFormatHelper::formatDate($action->action_date),
            action: $action->action,
            annotation: $action->annotation,
            term_start_date: $action->start_date ? DateFormatHelper::formatDate($action->start_date) : '-',
            term_end_date: $action->end_date ? DateFormatHelper::formatDate($action->end_date) : '-',
            registration_date: DateFormatHelper::formatDate($action->registration_date),
            alert_highlights: $alertHighlights,
            process_number: $process?->process_number,
            alert_level: $alertLevel,
        );
    }

    /**
     * start/end are relative to the field indicated by source (annotation or action) so the frontend can highlight in the correct place.
     *
     * @return array<int, array{start: int, end: int, text: string, source: string, action_start?: int, action_end?: int}>|null
     */
    private static function buildAlertHighlights(ProcessAction $action): ?array
    {
        $highlights = $action->relationLoaded('alertHighlights')
            ? $action->alertHighlights->sortBy('start')->values()
            : $action->alertHighlights()->orderedByStart()->get();

        if ($highlights->isEmpty()) {
            return null;
        }

        $anno = trim($action->annotation ?? '');
        $act = trim($action->action ?? '');
        $annotationBoundary = mb_strlen($anno) + ($anno !== '' && $act !== '' ? 1 : 0);

        return $highlights->map(function ($h) use ($annotationBoundary): array {
            $source = $h->source ?? 'annotation';
            $positions = self::positionsInSource($h->start, $h->end, $source, $annotationBoundary);

            return array_merge($positions, [
                'text' => $h->detected_text,
                'source' => $source,
            ]);
        })->values()->all();
    }

    /**
     * @return array{start: int, end: int, action_start?: int, action_end?: int}
     */
    private static function positionsInSource(int $start, int $end, string $source, int $annotationBoundary): array
    {
        if ($source === 'annotation') {
            return ['start' => $start, 'end' => $end];
        }

        if ($source === 'action') {
            return [
                'start' => max(0, $start - $annotationBoundary),
                'end' => max(0, $end - $annotationBoundary),
            ];
        }

        return [
            'start' => $start,
            'end' => min($end, $annotationBoundary),
            'action_start' => max(0, $start - $annotationBoundary),
            'action_end' => max(0, $end - $annotationBoundary),
        ];
    }
}
