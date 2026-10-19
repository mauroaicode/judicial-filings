<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessActionIdentityHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;
use Src\Domain\Process\Services\GroupProcessActionsService;

/**
 * Builds consolidado table rows from pending (or collected) organization notifications.
 *
 * Source-agnostic: works for actuaciones from Rama, Samai, or any other ProcessAction origin.
 */
class BuildNotificationDigestRowsService
{
    public function __construct(
        private readonly GroupProcessActionsService $groupProcessActionsService,
    ) {}

    /**
     * @param  Collection<int, OrganizationNotification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Collection $notifications, string $organizationId): Collection
    {
        $digestData = $this->prepareData($notifications, $organizationId);

        return $this->groupAndMergeActions($digestData);
    }

    /**
     * @param  Collection<int, OrganizationNotification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    private function prepareData(Collection $notifications, string $organizationId): Collection
    {
        return $notifications->groupBy(function (OrganizationNotification $notif): string {
            $action = $notif->notifiable;

            if (! $action instanceof ProcessAction) {
                return 'orphan:'.$notif->id;
            }

            $action->loadMissing('process');
            $processNumber = (string) ($action->process->process_number ?? '');

            return $processNumber.'|'.ProcessActionIdentityHelper::fingerprint($action);
        })->map(function (Collection $group) use ($organizationId): ?array {
            /** @var Collection<int, ProcessAction> $actions */
            $actions = $group
                ->map(fn (OrganizationNotification $notif) => $notif->notifiable)
                ->filter(fn ($notifiable): bool => $notifiable instanceof ProcessAction)
                ->values();

            if ($actions->isEmpty()) {
                return null;
            }

            $actions->each(fn (ProcessAction $candidate) => $candidate->loadMissing('process'));

            $action = ProcessActionIdentityHelper::pickCanonical(
                $actions,
                $actions->map(fn (ProcessAction $candidate) => $candidate->process)->unique('id')->values(),
            );
            $process = $action->process;

            $parties = $process->subjects()
                ->select(['subject_type', 'name_or_business_name'])
                ->get();

            $demandanteNames = $parties->where('subject_type', 'Demandante')->pluck('name_or_business_name')->unique()->sort()->values();
            $demandante = $demandanteNames->map(fn (?string $name): ?string => StrParseHelper::toTitleCase($name))->implode(', ');

            $demandadoNames = $parties->where('subject_type', 'Demandado')->pluck('name_or_business_name')->unique()->sort()->values();
            $demandado = $demandadoNames->map(fn (?string $name): ?string => StrParseHelper::toTitleCase($name))->implode(', ');

            $isAlert = $group->contains('notification_type', 'actuacion_alerta');
            $isRegistrationAlert = $group->contains('notification_type', 'actuacion_registro');
            $matchedKeywords = null;
            $alertHighlights = [];

            if ($isAlert) {
                $highlights = ProcessActionAlertHighlight::query()
                    ->where('process_action_id', $action->id)
                    ->where('organization_id', $organizationId)
                    ->get()
                    ->unique(fn ($h): string => "{$h->start}-{$h->end}-{$h->detected_text}-{$h->source}");

                $matchedKeywords = $highlights->pluck('detected_text')->unique()->implode(', ');

                $alertHighlights = $highlights->map(fn ($h): array => [
                    'start' => $h->start,
                    'end' => $h->end,
                    'text' => $h->detected_text,
                    'source' => $h->source,
                ])->all();
            }

            return [
                'process_action_id' => $action->id,
                'process_id' => $process->id,
                'court' => StrParseHelper::toTitleCase($process->court),
                'process_number' => $process->process_number,
                'demandante' => empty($demandante) ? '---' : $demandante,
                'demandado' => empty($demandado) ? '---' : $demandado,
                'action_date' => DateFormatHelper::formatDate($action->action_date),
                'action_text' => StrParseHelper::toTitleCase((string) $action->action),
                'annotation' => StrParseHelper::toTitleCase((string) ($action->annotation ?: '---')),
                'term_start_date' => $action->start_date ? DateFormatHelper::formatDate($action->start_date) : null,
                'term_end_date' => $action->end_date ? DateFormatHelper::formatDate($action->end_date) : null,
                'registration_date' => DateFormatHelper::formatDate($action->registration_date),
                'is_alert' => $isAlert,
                'is_registration_alert' => $isRegistrationAlert,
                'matched_keywords' => $matchedKeywords,
                'alert_highlights' => $alertHighlights,
            ];
        })->filter()->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @return Collection<int, array<string, mixed>>
     */
    private function groupAndMergeActions(Collection $data): Collection
    {
        $tagged = $this->groupProcessActionsService->handle($data);

        $toRemove = collect();
        $merged = $tagged->map(function (array $item) use ($tagged, $toRemove): array {
            if (isset($item['fijacion_action_id']) && $tagged->contains('process_action_id', $item['fijacion_action_id'])) {
                $toRemove->push($item['process_action_id'] ?? $item['id']);
            }

            if (isset($item['notified_action_id'])) {
                $auto = $tagged->firstWhere('process_action_id', $item['notified_action_id']);

                if ($auto) {
                    $item['is_merged'] = true;
                    $item['linked_action_text'] = $auto['action_text'];
                    $item['linked_annotation'] = $auto['annotation'];
                    $item['is_alert'] = $item['is_alert'] || $auto['is_alert'];
                    $item['matched_keywords'] = collect([$item['matched_keywords'], $auto['matched_keywords']])
                        ->filter()
                        ->unique()
                        ->implode(', ');
                }
            }

            return $item;
        });

        return $merged->reject(fn (array $item) => $toRemove->contains($item['process_action_id'] ?? $item['id']))->values();
    }
}
