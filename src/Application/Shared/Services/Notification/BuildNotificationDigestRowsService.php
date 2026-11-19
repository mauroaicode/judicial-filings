<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification;

use Illuminate\Support\Collection;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessActionIdentityHelper;
use Src\Application\Shared\Helpers\SamaiCourtNameHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

/**
 * Builds consolidado table rows from pending (or collected) organization notifications.
 *
 * Source-agnostic: works for actuaciones from Rama, Samai, or any other ProcessAction origin.
 */
class BuildNotificationDigestRowsService
{
    public function __construct(
        private readonly AssembleConsolidadoDigestRowsService $assembleConsolidadoDigestRowsService,
    ) {}

    /**
     * @param  Collection<int, OrganizationNotification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    public function handle(Collection $notifications, string $organizationId): Collection
    {
        $digestData = $this->prepareData($notifications, $organizationId);

        return $this->assembleConsolidadoDigestRowsService->handle($digestData);
    }

    /**
     * @param  Collection<int, OrganizationNotification>  $notifications
     * @return Collection<int, array<string, mixed>>
     */
    private function prepareData(Collection $notifications, string $organizationId): Collection
    {
        /** @var Collection<int, array<string, mixed>> $rows */
        $rows = $notifications->groupBy(function (OrganizationNotification $notif): string {
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
                'court' => SamaiCourtNameHelper::forDisplay($process->court),
                'process_number' => $process->process_number,
                'demandante' => empty($demandante) ? '---' : $demandante,
                'demandado' => empty($demandado) ? '---' : $demandado,
                'action_date' => DateFormatHelper::formatDate($action->action_date),
                'action_date_iso' => DateFormatHelper::formatIsoDate($action->action_date),
                'action_text' => StrParseHelper::toTitleCase((string) $action->action),
                'annotation' => StrParseHelper::toTitleCase((string) ($action->annotation ?: '---')),
                'term_start_date' => $action->start_date ? DateFormatHelper::formatDate($action->start_date) : null,
                'term_end_date' => $action->end_date ? DateFormatHelper::formatDate($action->end_date) : null,
                'registration_date' => DateFormatHelper::formatDate($action->registration_date),
                'registration_date_iso' => DateFormatHelper::formatIsoDate($action->registration_date),
                'cons_action' => $action->cons_action,
                'is_alert' => $isAlert,
                'is_registration_alert' => $isRegistrationAlert,
                'matched_keywords' => $matchedKeywords,
                'alert_highlights' => $alertHighlights,
            ];
        })->filter()->values();

        return $rows;
    }
}
