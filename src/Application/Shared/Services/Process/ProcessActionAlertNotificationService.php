<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        private ProcessActionKeywordDetectionService $keywordDetection
    ) {}

    /**
     * Entry point to process notifications and alerts for a new judicial action.
     */
    public function handle(ProcessAction $action, Process $process): void
    {
        $organizations = $this->getInterestedOrganizations($process);

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('ProcessActionAlertNotificationService: checking interested organizations', [
                'process_id' => $process->id,
                'organizations_count' => $organizations->count(),
            ]);

        foreach ($organizations as $organization) {
            $this->notifyNewAction($action, $organization->id);

            $this->processAlertsForOrganization($action, $organization);
        }
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
    private function processAlertsForOrganization(ProcessAction $action, Organization $organization): void
    {
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

        $this->notifyAlertDetected($action, $organization->id, $bestColor);

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
    private function notifyNewAction(ProcessAction $action, string $organizationId): void
    {
        $this->createNotificationAndDispatch($action, $organizationId, 'actuacion');
    }

    /**
     * Dispatch a keyword-triggered "alert" notification.
     */
    private function notifyAlertDetected(ProcessAction $action, string $organizationId, ?string $severityColor = null): void
    {
        $this->createNotificationAndDispatch($action, $organizationId, 'actuacion_alerta', $severityColor);
    }

    /**
     * Create a notification record and dispatch the notification job.
     */
    private function createNotificationAndDispatch(ProcessAction $action, string $organizationId, string $notificationType, ?string $severityColor = null): void
    {
        OrganizationNotification::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_id' => $action->id,
                'notifiable_type' => $action->getMorphClass(),
                'notification_type' => $notificationType,
                'severity_color' => $severityColor,
            ],
            [
                'id' => (string) Str::uuid(),
                'is_viewed' => false,
                'is_notified' => false,
            ]
        );

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->info('Notification recorded for digest', [
                'organization_id' => $organizationId,
                'type' => $notificationType,
                'color' => $severityColor,
                'action_id' => $action->id,
            ]);
    }
}
