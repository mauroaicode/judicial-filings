<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Collection;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;

readonly class ResolveProcessActionNotifiedConsolidadosService
{
    /**
     * @param  Collection<int, ProcessAction>|list<ProcessAction>  $actions
     * @return array<string, list<array<string, mixed>>>
     */
    public function handle(Collection|array $actions): array
    {
        $collection = $actions instanceof Collection ? $actions : collect($actions);

        $actionIds = $collection
            ->filter(fn (ProcessAction $action): bool => $action->removed_from_judicial_branch_at !== null)
            ->pluck('id')
            ->all();

        if ($actionIds === []) {
            return [];
        }

        $morphClass = (new ProcessAction)->getMorphClass();

        $rows = OrganizationNotification::query()
            ->where('notifiable_type', $morphClass)
            ->whereIn('notifiable_id', $actionIds)
            ->whereNotNull('notification_digest_id')
            ->with([
                'organization:id,name',
            ])
            ->get(['id', 'organization_id', 'notifiable_id', 'notification_digest_id', 'notification_type']);

        $digestIds = $rows->pluck('notification_digest_id')->filter()->unique()->values()->all();

        /** @var Collection<string, NotificationDigest> $digests */
        $digests = $digestIds === []
            ? collect()
            : NotificationDigest::query()
                ->whereIn('id', $digestIds)
                ->get(['id', 'created_at', 'email_sent_at'])
                ->keyBy('id');

        $grouped = [];

        foreach ($rows as $notification) {
            $digest = $digests->get((string) $notification->notification_digest_id);
            if (! $digest instanceof NotificationDigest) {
                continue;
            }

            $grouped[(string) $notification->notifiable_id][] = [
                'organization_id' => $notification->organization_id,
                'organization_name' => $notification->organization->name ?? '',
                'notification_digest_id' => $digest->id,
                'notification_type' => $notification->notification_type,
                'consolidado_date' => DateFormatHelper::formatDate($digest->created_at),
                'email_sent_at' => $digest->email_sent_at
                    ? DateFormatHelper::formatDateWithTime($digest->email_sent_at)
                    : null,
            ];
        }

        return $grouped;
    }
}
