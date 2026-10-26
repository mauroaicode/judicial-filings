<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Notification\NotificationDigestAccess;
use Src\Domain\Notification\Models\NotificationDigest;

class NotificationDigestHistoryResource extends Resource
{
    public function __construct(
        public string $id,
        public string $organization_id,
        public string $organization_name,
        public string $date,
        public string $time,
        public string $period,
        public int $actions_count,
        public bool $is_notified,
        public ?string $email_notified_at,
        public ?string $whatsapp_notified_at,
        public ?string $sms_notified_at,
        public bool $is_viewed,
        public ?string $viewed_at,
    ) {}

    public static function fromModel(NotificationDigest $digest): self
    {
        $history = NotificationDigestAccess::history($digest);

        return new self(
            id: $history->id,
            organization_id: $digest->organization_id,
            organization_name: $digest->organization->name,
            date: $history->date,
            time: $history->time,
            period: $history->period,
            actions_count: $history->actions_count,
            is_notified: $history->is_notified,
            email_notified_at: $history->email_notified_at,
            whatsapp_notified_at: $history->whatsapp_notified_at,
            sms_notified_at: $history->sms_notified_at,
            is_viewed: $digest->viewed_at !== null,
            viewed_at: $digest->viewed_at
                ? DateFormatHelper::formatDateWithTime($digest->viewed_at)
                : null,
        );
    }
}
