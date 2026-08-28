<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Application\Shared\Services\Notification\OrganizationNotificationRegistrationCutoffService;
use Src\Domain\Keyword\Enums\KeywordStatus;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\AlertActionKeyword;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessActionAlertHighlight;

readonly class ProcessActionAlertNotificationService
{
    public function __construct(
        private ProcessActionKeywordDetectionService $keywordDetection,
        private OrganizationNotificationRegistrationCutoffService $registrationCutoffService,
    ) {}

    /**
     * Entry point to process notifications and alerts for a new judicial action.
     */
    public function handle(ProcessAction $action, Process $process, bool $forceIncludeHistorical = false): void
    {
        $organizations = $this->getInterestedOrganizations($process);

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('ProcessActionAlertNotificationService: checking interested organizations', [
                'process_id' => $process->id,
                'organizations_count' => $organizations->count(),
                'force_include_historical' => $forceIncludeHistorical,
            ]);

        foreach ($organizations as $organization) {
            $this->handleForOrganization($action, $process, $organization->id, 'actuacion', $forceIncludeHistorical);
        }
    }

    /**
     * Notify a single organization only (used during manual/bulk registration).
     */
    public function handleForOrganization(
        ProcessAction $action,
        Process $process,
        string $organizationId,
        string $baseNotificationType = 'actuacion',
        bool $forceIncludeHistorical = false,
    ): void {
        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            return;
        }

        $organization->load(['keywords' => fn ($q) => $q->where('status', KeywordStatus::ACTIVE)]);

        $this->notifyNewAction($action, $organizationId, $baseNotificationType, $forceIncludeHistorical);
        $this->processAlertsForOrganization($action, $organization, $forceIncludeHistorical);
    }

    /**
     * Registration/import: list individual actuaciones in app-user notifications and queue digest rows.
     */
    public function handleForOrganizationRegistration(
        ProcessAction $action,
        Process $process,
        string $organizationId,
    ): void {
        $organization = Organization::query()->find($organizationId);

        if ($organization === null) {
            return;
        }

        $organization->load(['keywords' => fn ($q) => $q->where('status', KeywordStatus::ACTIVE)]);

        $this->notifyNewAction($action, $organizationId, 'actuacion');
        $this->notifyNewAction($action, $organizationId, 'actuacion_registro');
        $this->processAlertsForOrganization($action, $organization);
    }

    /**
     * Fetch active organizations interested in the process with their keywords.
     *
     * @return Collection<int, Organization>
     */
    private function getInterestedOrganizations(Process $process): Collection
    {
        return $process->organizations()
            ->wherePivot('is_active', true)
            ->with(['keywords' => fn ($q) => $q->where('status', KeywordStatus::ACTIVE)])
            ->get();
    }

    /**
     * Execute keyword detection and coordinate highlighting for a specific organization.
     */
    private function processAlertsForOrganization(
        ProcessAction $action,
        Organization $organization,
        bool $forceIncludeHistorical = false,
    ): void {
        $detectionResults = $this->keywordDetection->handle($action, $organization->keywords);

        if ($detectionResults->isEmpty()) {
            return;
        }

        // Determine highest severity
        $bestColor = null;
        $order = ['red' => 3, 'yellow' => 2, 'green' => 1];

        foreach ($detectionResults as $result) {
            $color = $result['keyword']->severity_color;
            if ($color && (! $bestColor || ($order[$color] ?? 0) > ($order[$bestColor] ?? 0))) {
                $bestColor = $color;
            }
        }

        $this->notifyAlertDetected($action, $organization->id, $bestColor, $forceIncludeHistorical);

        $this->registerHighlightsAndGlobalKeywords($action, $organization->id, $detectionResults);
    }

    /**
     * Persist position-based highlights and map hits to global alert categories.
     */
    private function registerHighlightsAndGlobalKeywords(ProcessAction $action, string $organizationId, \Illuminate\Support\Collection $detectionResults): void
    {
        $globalKeywordIds = [];

        foreach ($detectionResults as $result) {
            foreach ($result['matches'] as $match) {
                ProcessActionAlertHighlight::query()->create([
                    'process_action_id' => $action->id,
                    'organization_id' => $organizationId,
                    'start' => $match['start'],
                    'end' => $match['end'],
                    'detected_text' => $match['text'],
                    'source' => $match['source'],
                ]);

                $globalKeyword = AlertActionKeyword::matchFragment($match['text']);
                if ($globalKeyword instanceof \Src\Domain\Process\Models\AlertActionKeyword) {
                    $globalKeywordIds[$globalKeyword->id] = true;
                }
            }
        }

        if ($globalKeywordIds !== []) {
            $action->alertActionKeywords()->syncWithoutDetaching(array_keys($globalKeywordIds));
        }
    }

    /**
     * Dispatch a standard "new action" notification.
     */
    private function notifyNewAction(
        ProcessAction $action,
        string $organizationId,
        string $notificationType = 'actuacion',
        bool $forceIncludeHistorical = false,
    ): void {
        $this->createNotificationAndDispatch($action, $organizationId, $notificationType, null, $forceIncludeHistorical);
    }

    /**
     * Dispatch a keyword-triggered "alert" notification.
     */
    private function notifyAlertDetected(
        ProcessAction $action,
        string $organizationId,
        ?string $severityColor = null,
        bool $forceIncludeHistorical = false,
    ): void {
        $this->createNotificationAndDispatch($action, $organizationId, 'actuacion_alerta', $severityColor, $forceIncludeHistorical);
    }

    /**
     * Create a notification record and dispatch the notification job.
     *
     * Cross-instance deduplication: the same actuación (identified by
     * action_registration_id) can appear in multiple process instances when
     * courts publish the same entry under several radicado duplicates.
     * Before creating a new record, verify that no notification already exists
     * for ANY ProcessAction sharing the same action_registration_id for this
     * organization/type/severity combination. This prevents repeated alerts
     * to the client for what is logically one single actuación.
     */
    private function createNotificationAndDispatch(
        ProcessAction $action,
        string $organizationId,
        string $notificationType,
        ?string $severityColor = null,
        bool $forceIncludeHistorical = false,
    ): void {
        $logChannel = config('judicial-sync.log_channel', 'judicial_sync_notifications');

        // Check for an existing notification across all instances of this actuación (by content).
        $action->loadMissing('process');
        $processNumber = (string) ($action->process->process_number ?? '');
        $morphClass = $action->getMorphClass();

        $matchingActionIds = $processNumber !== ''
            ? ProcessAction::query()
                ->whereMatchesContentIdentity($processNumber, $action)
                ->pluck('id')
            : collect([$action->id]);

        $crossInstanceDuplicate = OrganizationNotification::query()
            ->where('organization_id', $organizationId)
            ->where('notification_type', $notificationType)
            ->where('severity_color', $severityColor)
            ->where('notifiable_type', $morphClass)
            ->whereIn('notifiable_id', $matchingActionIds->all())
            ->exists();

        if ($crossInstanceDuplicate) {
            Log::channel($logChannel)->info('ProcessActionAlertNotificationService: skipping cross-instance duplicate notification', [
                'action_registration_id' => $action->action_registration_id,
                'action_id' => $action->id,
                'organization_id' => $organizationId,
                'type' => $notificationType,
            ]);

            return;
        }

        if (! $forceIncludeHistorical && $this->shouldSkipHistoricalActuacionNotification($action, $organizationId, $notificationType)) {
            Log::channel($logChannel)->info('ProcessActionAlertNotificationService: skipping historical actuacion notification', [
                'action_id' => $action->id,
                'organization_id' => $organizationId,
                'type' => $notificationType,
                'registration_date' => $action->registration_date->format('Y-m-d'),
                'floor' => $this->registrationCutoffService->resolveAppNotificationRegistrationFloor(),
            ]);

            return;
        }

        OrganizationNotification::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_id' => $action->id,
                'notifiable_type' => $morphClass,
                'notification_type' => $notificationType,
                'severity_color' => $severityColor,
            ],
            [
                'id' => (string) Str::uuid(),
                'is_viewed' => false,
                'is_notified' => false,
            ]
        );

        Log::channel($logChannel)
            ->info('Notification recorded for digest', [
                'organization_id' => $organizationId,
                'type' => $notificationType,
                'color' => $severityColor,
                'action_id' => $action->id,
            ]);
    }

    private function shouldSkipHistoricalActuacionNotification(
        ProcessAction $action,
        string $organizationId,
        string $notificationType,
    ): bool {
        if (! in_array($notificationType, ['actuacion', 'actuacion_alerta'], true)) {
            return false;
        }

        return ! $this->registrationCutoffService->isEligibleForAppActuacionNotification($action, $organizationId);
    }
}
