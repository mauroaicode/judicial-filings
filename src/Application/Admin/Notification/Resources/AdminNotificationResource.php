<?php

declare(strict_types=1);

namespace Src\Application\Admin\Notification\Resources;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Date;
use Spatie\LaravelData\Resource;

class AdminNotificationResource extends Resource
{
    public function __construct(
        public string $id,
        public string $type,
        public string $notifiable_type,
        public string|int $notifiable_id,
        public array $data,
        public ?string $read_at,
        public ?string $opened_at,
        public ?string $created_at,
        public ?string $updated_at,
        public ?string $created_at_human, // This adds the requested human-readable date key
    ) {}

    public static function fromModel(DatabaseNotification $notification): self
    {
        return new self(
            id: $notification->id,
            type: $notification->type,
            notifiable_type: $notification->notifiable_type,
            notifiable_id: $notification->notifiable_id,
            data: $notification->data,
            read_at: $notification->read_at?->toISOString(),
            opened_at: isset($notification->opened_at)
                ? Date::parse($notification->opened_at)->toISOString()
                : null,
            created_at: $notification->created_at?->toISOString(),
            updated_at: $notification->updated_at?->toISOString(),
            created_at_human: $notification->created_at?->diffForHumans(),
        );
    }
}
