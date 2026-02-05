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
use Src\Domain\Process\Models\ProcessAction;

readonly class OrganizationNotificationListService
{
    private const VALID_TYPES = ['actuacion', 'actuacion_alerta'];

    public function handle(string $organizationId, string $type, bool $viewed, int $perPage, int $page): OrganizationNotificationListResource
    {
        $this->validateType($type);

        $paginator = $this->getPaginatedNotifications($organizationId, $type, $viewed, $perPage, $page);
        $items = $this->mapNotificationsToItems(collect($paginator->items()));

        return OrganizationNotificationListResource::fromPaginator($type, $items, $paginator);
    }

    private function validateType(string $type): void
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            abort(422, __('validation.invalid', ['attribute' => 'type']));
        }
    }

    private function getPaginatedNotifications(string $organizationId, string $type, bool $viewed, int $perPage, int $page): LengthAwarePaginator
    {
        return OrganizationNotification::query()
            ->whereOrganization($organizationId)
            ->whereNotificationType($type)
            ->whereViewed($viewed)
            ->with(['notifiable' => fn ($q) => $q->with(['process' => fn ($q2) => $q2->with('subjects')], 'alertHighlights')])
            ->orderedByNotifiableActionDate()
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
        ];

        $subjects = $process->relationLoaded('subjects')
            ? $process->subjects
            : $process->subjects()->orderedBySubjectType()->get();

        $detail['subjects'] = $subjects->map(fn ($s): array => [
            'name' => StrParseHelper::toTitleCase($s->name_or_business_name) ?? '',
            'type' => StrParseHelper::toTitleCase($s->subject_type) ?? '',
        ])->values()->all();

        $highlights = $action->relationLoaded('alertHighlights')
            ? $action->alertHighlights->sortBy('start')->values()
            : $action->alertHighlights()->orderedByStart()->get();

        if ($highlights->isNotEmpty()) {
            $detail['alert_highlights'] = $highlights->map(fn ($h): array => [
                'start' => $h->start,
                'end' => $h->end,
                'text' => $h->detected_text,
            ])->values()->all();
        }

        return $detail;
    }
}
