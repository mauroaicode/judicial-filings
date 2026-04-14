<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Src\Application\AppUser\OrganizationNotification\Resources\OrganizationNotificationListResource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\AlertActionKeyword;
use Src\Domain\Process\Models\ProcessAction;

readonly class OrganizationNotificationListService
{
    private const VALID_TYPES = ['actuacion', 'actuacion_alerta'];

    public function handle(string $organizationId, string $type, bool $viewed, int $perPage, int $page, ?string $alertSlug = null): OrganizationNotificationListResource
    {
        $this->validateType($type);

        $paginator = $this->getPaginatedNotifications($organizationId, $type, $viewed, $perPage, $page, $alertSlug);
        $items = $this->mapNotificationsToItems(collect($paginator->items()));

        return OrganizationNotificationListResource::fromPaginator($type, $items, $paginator);
    }

    private function validateType(string $type): void
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            abort(422, __('validation.invalid', ['attribute' => 'type']));
        }
    }

    private function getPaginatedNotifications(string $organizationId, string $type, bool $viewed, int $perPage, int $page, ?string $alertSlug = null): LengthAwarePaginator
    {
        $query = OrganizationNotification::query()
            ->whereOrganization($organizationId)
            ->whereNotificationType($type)
            ->whereViewed($viewed)
            ->with(['notifiable' => fn ($q) => $q->with(['process' => fn ($q2) => $q2->with(['subjects' => fn ($q3) => $q3->orderedByPriority()])], 'alertHighlights')]);

        if ($alertSlug !== null && $alertSlug !== '' && $type === 'actuacion_alerta') {
            $keyword = AlertActionKeyword::query()->where('slug', $alertSlug)->first();
            if ($keyword !== null) {
                $query->whereHasMorph('notifiable', [ProcessAction::class], function (\Illuminate\Contracts\Database\Query\Builder $q) use ($keyword): void {
                    $q->whereHas('alertActionKeywords', fn (\Illuminate\Contracts\Database\Query\Builder $q2) => $q2->where('id', $keyword->id));
                });
            }
        }

        return $query
            ->orderedByCreatedAt()
            ->paginate($perPage, ['*'], 'page', $page)
            ->appends(request()->query());
    }

    /**
     * @param  Collection<int, OrganizationNotification>  $notifications
     * @return array<int, array{notification_id: string, notification_time_human: string, detail: array<string, mixed>}>
     */
    private function mapNotificationsToItems($notifications): array
    {
        return $notifications->map(function (OrganizationNotification $notification): array {
            $notifiable = $notification->notifiable;
            $detail = $this->buildDetail($notifiable);

            return [
                'notification_id' => (string) $notification->id,
                'notification_time_human' => $this->formatNotificationTime($notification->created_at),
                'detail' => $detail,
            ];
        })->all();
    }

    private function formatNotificationTime(Carbon $createdAt): string
    {
        $locale = app()->getLocale();

        return $createdAt->locale($locale)->diffForHumans();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDetail(?object $notifiable): array
    {
        if ($notifiable instanceof ProcessAction) {
            return $this->buildActionDetail($notifiable);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildActionDetail(ProcessAction $action): array
    {
        $process = $action->process;

        $detail = [
            'process_id' => $process->id,
            'process_number' => $process->process_number,
            'action' => $action->action,
            'annotation' => $action->annotation,
            'action_date' => DateFormatHelper::formatDate($action->action_date),
            'registration_date' => DateFormatHelper::formatDate($action->registration_date),
            'term_start_date' => $action->start_date ? DateFormatHelper::formatDate($action->start_date) : '-',
            'term_end_date' => $action->end_date ? DateFormatHelper::formatDate($action->end_date) : '-',
        ];

        $subjects = $process->relationLoaded('subjects')
            ? $process->subjects
            : $process->subjects()->orderedByPriority()->get();

        // Sort collection to ensure order even if loaded through other routes
        $subjects = $subjects->sortBy([
            function ($subject): int {
                $type = mb_strtoupper((string) $subject->subject_type);
                if (str_contains($type, 'DEMANDANTE')) {
                    return 1;
                }

                if (str_contains($type, 'DEMANDADO')) {
                    return 2;
                }

                return 3;
            },
            fn ($subject) => mb_strtolower((string) $subject->name_or_business_name),
        ]);

        $detail['subjects'] = $subjects->map(fn ($s): array => [
            'name' => StrParseHelper::toTitleCase($s->name_or_business_name) ?? '',
            'type' => StrParseHelper::toTitleCase($s->subject_type) ?? '',
        ])->values()->all();

        $highlights = $action->relationLoaded('alertHighlights')
            ? $action->alertHighlights->sortBy('start')->values()
            : $action->alertHighlights()->orderedByStart()->get();

        if ($highlights->isNotEmpty()) {
            $anno = trim($action->annotation ?? '');
            $act = trim($action->action ?? '');
            $annotationBoundary = mb_strlen($anno) + ($anno !== '' && $act !== '' ? 1 : 0);

            $detail['alert_highlights'] = $highlights->map(function ($h) use ($annotationBoundary): array {
                $keyword = AlertActionKeyword::matchFragment($h->detected_text);
                $source = $h->source ?? 'annotation';
                $positions = $this->highlightPositionsInSource($h->start, $h->end, $source, $annotationBoundary);

                return array_merge($positions, [
                    'text' => $h->detected_text,
                    'source' => $source,
                    'alert_type' => $keyword instanceof AlertActionKeyword
                        ? ['id' => $keyword->id, 'name' => $keyword->name, 'slug' => $keyword->slug]
                        : null,
                ]);
            })->values()->all();
        }

        return $detail;
    }

    /**
     * Return start/end relative to the field indicated by source so the frontend can highlight in the correct place.
     * - source "annotation" → start, end are positions within the annotation string.
     * - source "action" → start, end are positions within the action string.
     * - source "both" → start, end for the annotation part; action_start, action_end for the action part.
     *
     * @return array{start: int, end: int, action_start?: int, action_end?: int}
     */
    private function highlightPositionsInSource(int $start, int $end, string $source, int $annotationBoundary): array
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

        // both: span crosses annotation and action
        $annotationEnd = min($end, $annotationBoundary);
        $actionStart = max(0, $start - $annotationBoundary);
        $actionEnd = max(0, $end - $annotationBoundary);

        return [
            'start' => $start,
            'end' => $annotationEnd,
            'action_start' => $actionStart,
            'action_end' => $actionEnd,
        ];
    }
}
