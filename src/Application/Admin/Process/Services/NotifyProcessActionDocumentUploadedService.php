<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Domain\Media\Models\Media;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessAction;

readonly class NotifyProcessActionDocumentUploadedService
{
    public const NOTIFICATION_TYPE = 'actuacion_documento';

    /**
     * Immediate notify for every active interested org that can view action documents.
     *
     * @param  Collection<int, Media>  $mediaItems
     */
    public function handle(ProcessAction $action, Collection $mediaItems): void
    {
        if ($mediaItems->isEmpty()) {
            return;
        }

        $organizations = Organization::query()
            ->where('can_view_action_documents', true)
            ->where('is_active', true)
            ->whereHas('processes', function (\Illuminate\Contracts\Database\Query\Builder $query) use ($action): void {
                $query->where('processes.id', $action->process_id);
            })
            ->get();

        if ($organizations->isEmpty()) {
            return;
        }

        $morphClass = $action->getMorphClass();

        foreach ($organizations as $organization) {
            $notification = OrganizationNotification::query()->firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'notifiable_id' => $action->id,
                    'notifiable_type' => $morphClass,
                    'notification_type' => self::NOTIFICATION_TYPE,
                    'severity_color' => 'document',
                ],
                [
                    'id' => (string) Str::uuid(),
                    'is_viewed' => false,
                    'is_notified' => false,
                ]
            );

            if (! $notification->wasRecentlyCreated) {
                $notification->forceFill([
                    'is_viewed' => false,
                    'is_notified' => false,
                    'is_email_notified' => false,
                    'viewed_at' => null,
                    'notified_at' => null,
                    'email_notified_at' => null,
                ])->save();
            }

            dispatch(SendOrganizationNotificationJob::fromNotification($notification));
        }
    }
}
