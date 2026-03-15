<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Domain\Keyword\Enums\KeywordStatus;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Events\JudicialActionDetected;
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
     *
     * @param ProcessAction $action
     * @param Process $process
     * @return void
     */
    public function handle(ProcessAction $action, Process $process): void
    {
        $organizations = $this->getInterestedOrganizations($process);

        foreach ($organizations as $organization) {
            $this->notifyNewAction($action, $organization->id);

            $this->processAlertsForOrganization($action, $organization);
        }
    }

    /**
     * Fetch active organizations interested in the process with their keywords.
     *
     * @param Process $process
     * @return Collection
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
     *
     * @param ProcessAction $action
     * @param Organization $organization
     * @return void
     */
    private function processAlertsForOrganization(ProcessAction $action, Organization $organization): void
    {
        $detectionResults = $this->keywordDetection->handle($action, $organization->keywords);

        if ($detectionResults->isEmpty()) {
            return;
        }

        $this->notifyAlertDetected($action, $organization->id);

        $this->registerHighlightsAndGlobalKeywords($action, $organization->id, $detectionResults);
    }

    /**
     * Persist position-based highlights and map hits to global alert categories.
     *
     * @param ProcessAction $action
     * @param string $organizationId
     * @param \Illuminate\Support\Collection $detectionResults
     * @return void
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
                if ($globalKeyword) {
                    $globalKeywordIds[$globalKeyword->id] = true;
                }
            }
        }

        if (! empty($globalKeywordIds)) {
            $action->alertActionKeywords()->syncWithoutDetaching(array_keys($globalKeywordIds));
        }
    }

    /**
     * Dispatch a standard "new action" notification.
     *
     * @param ProcessAction $action
     * @param string $organizationId
     * @return void
     */
    private function notifyNewAction(ProcessAction $action, string $organizationId): void
    {
        $this->createNotificationAndDispatch($action, $organizationId, 'actuacion');
    }

    /**
     * Dispatch a keyword-triggered "alert" notification.
     *
     * @param ProcessAction $action
     * @param string $organizationId
     * @return void
     */
    private function notifyAlertDetected(ProcessAction $action, string $organizationId): void
    {
        $this->createNotificationAndDispatch($action, $organizationId, 'actuacion_alerta');
    }

    /**
     * Create a notification record and dispatch the notification job.
     *
     * @param ProcessAction $action
     * @param string $organizationId
     * @param string $notificationType
     * @return void
     */
    private function createNotificationAndDispatch(ProcessAction $action, string $organizationId, string $notificationType): void
    {
        $notification = OrganizationNotification::query()->firstOrCreate(
            [
                'organization_id' => $organizationId,
                'notifiable_id' => $action->id,
                'notifiable_type' => $action->getMorphClass(),
                'notification_type' => $notificationType,
            ],
            [
                'id' => (string) Str::uuid(),
                'is_viewed' => false,
                'is_notified' => false,
            ]
        );

        dispatch(SendOrganizationNotificationJob::fromNotification($notification));

        event(new JudicialActionDetected($action, $organizationId, $notificationType));
    }
}
