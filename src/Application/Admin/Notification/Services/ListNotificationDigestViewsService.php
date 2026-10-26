<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Services;

use Illuminate\Database\Eloquent\Collection;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\NotificationDigestView;

class ListNotificationDigestViewsService
{
    /**
     * Who opened this consolidado, oldest first. Reading this list does not record a view.
     *
     * @return Collection<int, NotificationDigestView>
     */
    public function handle(NotificationDigest $digest): Collection
    {
        return $digest->views()
            ->with('appUser:id,name,last_name,email')
            ->oldest('viewed_at')
            ->orderBy('id')
            ->get();
    }
}
