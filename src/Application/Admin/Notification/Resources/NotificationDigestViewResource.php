<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Notification\Models\NotificationDigestView;

class NotificationDigestViewResource extends Resource
{
    public function __construct(
        public string $id,
        public string $app_user_id,
        public string $app_user_name,
        public string $app_user_last_name,
        public string $email,
        public string $viewed_at,
        public string $opened_via,
    ) {}

    public static function fromModel(NotificationDigestView $view): self
    {
        $appUser = $view->appUser;

        return new self(
            id: $view->id,
            app_user_id: $view->app_user_id,
            app_user_name: $appUser->name,
            app_user_last_name: $appUser->last_name,
            email: $appUser->email,
            viewed_at: DateFormatHelper::formatDateWithTime($view->viewed_at),
            opened_via: $view->opened_via->value,
        );
    }
}
