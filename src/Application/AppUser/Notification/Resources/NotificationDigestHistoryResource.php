<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Notification\Models\NotificationDigest;

class NotificationDigestHistoryResource extends Resource
{
    public function __construct(
        public string $id,
        public string $date,
        public string $time,
        public string $period,
        public int $actions_count,
        public bool $is_notified,
        public ?string $email_notified_at,
        public ?string $whatsapp_notified_at,
        public ?string $sms_notified_at,
    ) {}

    public static function fromModel(NotificationDigest $digest): self
    {
        $notified = $digest->email_sent_at !== null || $digest->whatsapp_sent_at !== null || $digest->sms_sent_at !== null;

        $actionsCount = 0;
        if (! empty($digest->data)) {
            $deduped = collect($digest->data)->unique(function (array $item) {
                $id = $item['process_action_id'] ?? '';
                $radicado = $item['process_number'] ?? '';
                $text = $item['action_text'] ?? '';
                $date = $item['action_date'] ?? '';
                $annotation = $item['annotation'] ?? '';

                return $id ?: md5($radicado.$text.$date.$annotation);
            })->values();

            // Inject temporary synthetic ID if missing so pairing mechanisms work
            $deduped = $deduped->map(function (array $item, int|string $index): array {
                if (empty($item['process_action_id']) && empty($item['id'])) {
                    $item['id'] = 'synth-'.md5(json_encode($item).$index);
                }

                return $item;
            });

            // Re-use Group Process Service to compute Condensed Count
            $groupService = resolve(\Src\Domain\Process\Services\GroupProcessActionsService::class);
            $tagged = $groupService->handle($deduped);

            // Calculate orphans
            $toRemove = collect();
            $tagged->each(function (array $item) use ($tagged, $toRemove): void {
                $myId = $item['process_action_id'] ?? $item['id'] ?? null;
                if ($myId && isset($item['fijacion_action_id'])) {
                    // Check if parent fijiacion exists
                    $parentId = $item['fijacion_action_id'];
                    $hasFijacion = $tagged->contains(fn (array $t): bool => ($t['process_action_id'] ?? $t['id'] ?? null) === $parentId);
                    if ($hasFijacion) {
                        $toRemove->push($myId);
                    }
                }
            });

            $actionsCount = $tagged->reject(function (array $item) use ($toRemove): bool {
                $myId = $item['process_action_id'] ?? $item['id'] ?? null;

                return $myId && $toRemove->contains($myId);
            })->count();
        }

        return new self(
            id: $digest->id,
            date: DateFormatHelper::formatDate($digest->created_at),
            time: $digest->created_at->format('g:ia'),
            period: DateFormatHelper::getPeriodFromHour((int) $digest->created_at->format('H')),
            actions_count: $actionsCount,
            is_notified: $notified,
            email_notified_at: $digest->email_sent_at ? DateFormatHelper::formatDateWithTime($digest->email_sent_at) : null,
            whatsapp_notified_at: $digest->whatsapp_sent_at ? DateFormatHelper::formatDateWithTime($digest->whatsapp_sent_at) : null,
            sms_notified_at: $digest->sms_sent_at ? DateFormatHelper::formatDateWithTime($digest->sms_sent_at) : null,
        );
    }
}
