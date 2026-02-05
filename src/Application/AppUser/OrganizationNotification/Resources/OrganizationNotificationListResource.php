<?php

declare(strict_types=1);

namespace Src\Application\AppUser\OrganizationNotification\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\LaravelData\Resource;

class OrganizationNotificationListResource extends Resource
{
    /**
     * @param  array<int, array{notification_id: string, detail: array<string, mixed>}>  $data
     */
    public function __construct(
        public string $notification_type,
        public array $data,
        public array $meta,
    ) {}

    public static function fromPaginator(string $notificationType, array $items, LengthAwarePaginator $paginator): self
    {
        return new self(
            notification_type: $notificationType,
            data: $items,
            meta: [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
