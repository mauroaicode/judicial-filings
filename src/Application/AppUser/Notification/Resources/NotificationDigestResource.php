<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Resources;

use Illuminate\Support\Facades\Date;
use Spatie\LaravelData\Resource;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Models\ProcessAction;

class NotificationDigestResource extends Resource
{
    public function __construct(
        public string $id,
        public array $data,
        public string $created_at,
        public ?string $email_sent_at = null,
        public ?string $whatsapp_sent_at = null,
        public ?string $sms_sent_at = null,
    ) {}

    public static function fromModel(NotificationDigest $digest, ?NotificationDigestFilterData $filters = null): self
    {
        $digest->load([
            'notifications.notifiable.alertHighlights',
            'notifications.notifiable.process',
        ]);

        $notifLookup = self::buildNotificationLookup($digest);

        $formattedData = array_map(function (array $item) use ($notifLookup, $filters): ?array {

            // 1. Internal filtering logic
            if ($filters instanceof NotificationDigestFilterData && ! self::shouldIncludeItem($item, $filters, $notifLookup)) {
                return null;
            }

            // 2. Prepare sorting metadata
            $item['_sort_timestamp'] = self::calculateSortTimestamp($item['registration_date'] ?? null);

            // 3. Normalization and Mappings
            $item = self::applyKeyMappings($item);

            // 4. Enrich with highlights, levels and subjects
            $item = self::enrichItemData($item, $notifLookup);

            // 5. Title Case Formatting
            $item = self::applyTitleCaseFormatting($item);

            // 6. Final Date Formatting
            return self::applyFinalDateFormatting($item);

        }, $digest->data);

        // Clean, Sort and Strip metadata
        $finalData = self::processFinalCollection($formattedData);

        return new self(
            id: $digest->id,
            data: $finalData,
            created_at: DateFormatHelper::formatDate($digest->created_at),
            email_sent_at: $digest->email_sent_at ? DateFormatHelper::formatDate($digest->email_sent_at) : null,
            whatsapp_sent_at: $digest->whatsapp_sent_at ? DateFormatHelper::formatDate($digest->whatsapp_sent_at) : null,
            sms_sent_at: $digest->sms_sent_at ? DateFormatHelper::formatDate($digest->sms_sent_at) : null,
        );
    }

    private static function buildNotificationLookup(NotificationDigest $digest): array
    {
        $notifLookup = [];
        foreach ($digest->notifications as $notif) {
            $action = $notif->notifiable;
            if ($action instanceof ProcessAction) {
                $key = "{$action->process->process_number}|{$action->action}";
                $notifLookup[$key] = $notif;
            }
        }
        return $notifLookup;
    }

    private static function shouldIncludeItem(array $item, NotificationDigestFilterData $filters, array $notifLookup): bool
    {
        // Filter by process number
        if ($filters->process_number && isset($item['process_number']) && ! str_contains((string) $item['process_number'], $filters->process_number)) {
            return false;
        }

        // Filter by dates
        if (! self::checkDateFilter($item['registration_date'] ?? null, $filters->registration_date_from, $filters->registration_date_to)) return false;
        if (! self::checkDateFilter($item['action_date'] ?? null, $filters->action_date_from, $filters->action_date_to)) return false;
        if (! self::checkDateFilter($item['term_start_date'] ?? $item['start_date'] ?? null, $filters->term_start_date_from, $filters->term_start_date_to)) return false;
        if (! self::checkDateFilter($item['term_end_date'] ?? $item['end_date'] ?? null, $filters->term_end_date_from, $filters->term_end_date_to)) return false;

        // Smart Filters (Alert Level & Role)
        if ($filters->alert_level || $filters->lawyer_role) {
            $lookupKey = ($item['process_number'] ?? '').'|'.($item['action_text'] ?? '');
            $matchedNotif = $notifLookup[$lookupKey] ?? null;

            if ($filters->alert_level) {
                $level = null;
                if ($matchedNotif) {
                    $orgProc = $matchedNotif->notifiable->process->organizations->firstWhere('id', $matchedNotif->organization_id);
                    $level = $orgProc?->pivot->inactivity_alert_level;
                }
                if ($level !== $filters->alert_level) return false;
            }

            if ($filters->lawyer_role) {
                $role = null;
                if ($matchedNotif) {
                    $orgProc = $matchedNotif->notifiable->process->organizations->firstWhere('id', $matchedNotif->organization_id);
                    $roleValue = $orgProc?->pivot->lawyer_role instanceof \Src\Domain\Process\Enums\ProcessLawyerRole
                        ? $orgProc->pivot->lawyer_role->value
                        : $orgProc?->pivot->lawyer_role;
                    $role = (string) $roleValue;
                }
                if ($role !== $filters->lawyer_role) return false;
            }
        }

        return true;
    }

    private static function checkDateFilter(?string $value, ?string $from, ?string $to): bool
    {
        if (! $from && ! $to) return true;
        if (! $value) return false;

        try {
            $date = str_contains((string) $value, '/') ? Date::createFromFormat('d/m/Y', (string) $value) : Date::parse((string) $value);
            if ($from && $date->lt(Date::parse($from)->startOfDay())) return false;
            if ($to && $date->gt(Date::parse($to)->endOfDay())) return false;
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    private static function calculateSortTimestamp(?string $rawRegDate): int
    {
        if (! $rawRegDate) return 0;
        try {
            return str_contains((string) $rawRegDate, '/')
                ? Date::createFromFormat('d/m/Y', (string) $rawRegDate)->timestamp
                : Date::parse((string) $rawRegDate)->timestamp;
        } catch (\Exception) {
            return 0;
        }
    }

    private static function applyKeyMappings(array $item): array
    {
        $mappings = [
            'demandante' => 'plaintiff',
            'demandado' => 'defendant',
            'start_date' => 'term_start_date',
            'end_date' => 'term_end_date',
        ];

        foreach ($mappings as $oldKey => $newKey) {
            if (array_key_exists($oldKey, $item)) {
                $item[$newKey] = $item[$oldKey];
                unset($item[$oldKey]);
            }
        }
        return $item;
    }

    private static function enrichItemData(array $item, array $notifLookup): array
    {
        $lookupKey = ($item['process_number'] ?? '').'|'.($item['action_text'] ?? '');
        /** @var OrganizationNotification|null $matchedNotif */
        $matchedNotif = $notifLookup[$lookupKey] ?? null;

        // 1. Alert Highlights
        if ($matchedNotif && (empty($item['alert_highlights']))) {
            $actionModel = $matchedNotif->notifiable;
            $item['alert_highlights'] = $actionModel->alertHighlights
                ->unique(fn ($h): string => "{$h->start}-{$h->end}-{$h->detected_text}-{$h->source}")
                ->map(fn ($h): array => [
                    'start' => $h->start,
                    'end' => $h->end,
                    'text' => $h->detected_text,
                    'source' => $h->source,
                ])->values()->all();
        }
        $item['alert_highlights'] ??= [];

        // 2. Alert Level
        if ($matchedNotif) {
            $orgProc = $matchedNotif->notifiable->process->organizations->firstWhere('id', $matchedNotif->organization_id);
            $item['alert_level'] = $orgProc?->pivot->inactivity_alert_level;
            
            $roleValue = $orgProc?->pivot->lawyer_role instanceof \Src\Domain\Process\Enums\ProcessLawyerRole
                ? $orgProc->pivot->lawyer_role->value
                : $orgProc?->pivot->lawyer_role;
            $item['lawyer_role'] = (string) $roleValue;
        } else {
            $item['alert_level'] ??= null;
            $item['lawyer_role'] ??= null;
        }

        // 3. Subjects (Title Case & Pluralization)
        $subjects = ['plaintiff' => 'plaintiffs', 'defendant' => 'defendants'];
        foreach ($subjects as $singular => $plural) {
            if (isset($item[$singular]) && is_string($item[$singular])) {
                $list = array_filter(array_map(trim(...), explode(',', $item[$singular])));
                if ($list !== []) {
                    $list = array_map(StrParseHelper::toTitleCase(...), $list);
                    $item[$plural] = $list;
                    $count = count($list);
                    $item[$singular] = $count > 1 ? "{$list[0]} (+".($count - 1).')' : $list[0];
                } else {
                    $item[$plural] = [];
                }
            } else {
                $item[$plural] ??= [];
            }
        }

        return $item;
    }

    private static function applyTitleCaseFormatting(array $item): array
    {
        foreach (['court', 'process_class', 'subclass_process'] as $field) {
            if (isset($item[$field]) && is_string($item[$field])) {
                $item[$field] = StrParseHelper::toTitleCase($item[$field]);
            }
        }
        return $item;
    }

    private static function applyFinalDateFormatting(array $item): array
    {
        foreach (['registration_date', 'action_date', 'term_start_date', 'term_end_date'] as $field) {
            if (isset($item[$field]) && is_string($item[$field]) && ($item[$field] !== '' && $item[$field] !== '0')) {
                try {
                    $dateObj = str_contains($item[$field], '/') ? Date::createFromFormat('d/m/Y', $item[$field]) : $item[$field];
                    $item[$field] = DateFormatHelper::formatDate($dateObj);
                } catch (\Exception) {}
            }
        }
        return $item;
    }

    private static function processFinalCollection(array $formattedData): array
    {
        // Filter out nulls
        $collection = array_values(array_filter($formattedData));

        // Sort descending
        usort($collection, fn (array $a, array $b): int => $b['_sort_timestamp'] <=> $a['_sort_timestamp']);

        // Strip sorting metadata
        return array_map(function (array $item) {
            unset($item['_sort_timestamp']);
            return $item;
        }, $collection);
    }
}
