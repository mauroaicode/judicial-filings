<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Notification\Enums\NotificationDigestOpenedVia;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\NotificationDigestView;

class RecordNotificationDigestViewService
{
    public function handle(
        string $organizationId,
        string $organizationName,
        string $digestId,
        AppUser $appUser,
        NotificationDigestOpenedVia $openedVia,
    ): void {
        $digest = NotificationDigest::query()
            ->whereKey($digestId)
            ->where('organization_id', $organizationId)
            ->first();

        if (! $digest instanceof NotificationDigest) {
            return;
        }

        $alreadyViewedByUser = NotificationDigestView::query()
            ->where('notification_digest_id', $digest->id)
            ->where('app_user_id', $appUser->id)
            ->exists();

        if ($alreadyViewedByUser) {
            return;
        }

        $viewedAt = now();

        try {
            NotificationDigestView::query()->create([
                'notification_digest_id' => $digest->id,
                'organization_id' => $organizationId,
                'app_user_id' => $appUser->id,
                'opened_via' => $openedVia,
                'viewed_at' => $viewedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return;
        }

        if ($digest->viewed_at === null) {
            NotificationDigest::query()
                ->whereKey($digest->id)
                ->whereNull('viewed_at')
                ->update(['viewed_at' => $viewedAt]);
        }

        event(new \Src\Domain\Notification\Events\NotificationDigestViewed($digest->id, $organizationId, $organizationName, $appUser->id, $appUser->name, $appUser->last_name, $viewedAt->toIso8601String(), $openedVia->value));
    }
}
