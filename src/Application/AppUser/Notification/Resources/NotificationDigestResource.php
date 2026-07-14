<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Resources;

use Illuminate\Support\Facades\Date;
use Spatie\LaravelData\Resource;
use Src\Application\AppUser\Notification\Data\NotificationDigestFilterData;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessAlertLevelHelper;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

class NotificationDigestResource extends Resource
{
    public function __construct(
        public string $id,
        public array $data,
        public string $date,
        public string $time,
        public string $period,
        public int $actions_count,
        public bool $is_notified,
        public ?string $email_notified_at = null,
        public ?string $whatsapp_notified_at = null,
        public ?string $sms_notified_at = null,
    ) {}

    public static function fromModel(NotificationDigest $digest, ?NotificationDigestFilterData $filters = null): self
    {
        $digest->load([
            'notifications.notifiable.alertHighlights',
            'notifications.notifiable.process.organizations',
            'notifications.notifiable.process.subjects',
        ]);

        $notifLookup = self::buildNotificationLookup($digest);
        $formattedData = self::formatRawItems($digest->data ?? [], $notifLookup, $digest->organization_id, $filters);
        $finalData = self::processFinalCollection($formattedData);

        return self::buildResource($digest, $finalData);
    }

    /**
     * Filter raw digest rows without hitting the database.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array<string, mixed>>
     */
    public static function filterRawItems(array $data, ?NotificationDigestFilterData $filters = null): array
    {
        if (! $filters instanceof NotificationDigestFilterData) {
            return $data;
        }

        return array_values(array_filter(
            $data,
            static fn (array $item): bool => self::shouldIncludeItem($item, $filters),
        ));
    }

    /**
     * Sort raw digest rows by registration date (desc).
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function sortRawItems(array $items): array
    {
        $withSortMeta = array_map(static function (array $item): array {
            $item['_sort_timestamp'] = self::calculateSortTimestamp($item['registration_date'] ?? null);

            return $item;
        }, $items);

        usort($withSortMeta, static fn (array $a, array $b): int => $b['_sort_timestamp'] <=> $a['_sort_timestamp']);

        return array_map(static function (array $item): array {
            unset($item['_sort_timestamp']);

            return $item;
        }, $withSortMeta);
    }

    /**
     * Enrich only the rows needed for the current page.
     *
     * @param  array<int, array<string, mixed>>  $pageItems
     * @return array<int, array<string, mixed>>
     */
    public static function formatItemsForPage(
        NotificationDigest $digest,
        array $pageItems,
        string $organizationId,
    ): array {
        if ($pageItems === []) {
            return [];
        }

        $actionIds = collect($pageItems)
            ->pluck('process_action_id')
            ->filter(static fn ($id): bool => is_string($id) && $id !== '' && ! str_starts_with($id, 'synth-'))
            ->unique()
            ->values()
            ->all();

        $notifLookup = self::loadNotificationLookupForActions($digest->id, $organizationId, $actionIds);

        $processNumbers = collect($pageItems)
            ->filter(static fn (array $item): bool => empty($item['process_id']) && ! empty($item['process_number']))
            ->pluck('process_number')
            ->unique()
            ->values()
            ->all();

        $processIds = collect($pageItems)
            ->pluck('process_id')
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $processesQuery = Process::query()->with('subjects');
        if ($processIds !== [] || $processNumbers !== []) {
            $processesQuery->where(function (\Illuminate\Contracts\Database\Query\Builder $q) use ($processIds, $processNumbers): void {
                if ($processIds !== []) {
                    $q->whereIn('id', $processIds);
                }

                if ($processNumbers !== []) {
                    $method = $processIds !== [] ? 'orWhereIn' : 'whereIn';
                    $q->{$method}('process_number', $processNumbers);
                }
            });
        } else {
            $processesQuery->whereRaw('1 = 0');
        }

        $processes = $processesQuery->get();
        $processIdsByNumber = $processes->pluck('id', 'process_number')->all();
        $processesById = $processes->keyBy('id');

        return array_map(static function (array $item) use ($notifLookup, $processIdsByNumber, $organizationId, $processesById): array {
            $item = self::applyKeyMappings($item);
            $item = self::enrichItemData($item, $notifLookup, $organizationId, $processIdsByNumber, $processesById);
            $item = self::applyTitleCaseFormatting($item);

            return self::applyFinalDateFormatting($item);
        }, $pageItems);
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildMetadata(NotificationDigest $digest, int $actionsCount): array
    {
        $notified = $digest->email_sent_at !== null || $digest->whatsapp_sent_at !== null || $digest->sms_sent_at !== null;

        return [
            'id' => $digest->id,
            'date' => DateFormatHelper::formatDate($digest->created_at),
            'time' => $digest->created_at->format('g:ia'),
            'period' => DateFormatHelper::getPeriodFromHour((int) $digest->created_at->format('H')),
            'actions_count' => $actionsCount,
            'is_notified' => $notified,
            'email_notified_at' => $digest->email_sent_at ? DateFormatHelper::formatDateWithTime($digest->email_sent_at) : null,
            'whatsapp_notified_at' => $digest->whatsapp_sent_at ? DateFormatHelper::formatDateWithTime($digest->whatsapp_sent_at) : null,
            'sms_notified_at' => $digest->sms_sent_at ? DateFormatHelper::formatDateWithTime($digest->sms_sent_at) : null,
        ];
    }

    /**
     * @param  array<int, string>  $actionIds
     * @return array<string, OrganizationNotification>
     */
    public static function loadNotificationLookupForActions(
        string $digestId,
        string $organizationId,
        array $actionIds,
    ): array {
        if ($actionIds === []) {
            return [];
        }

        $lookup = [];

        OrganizationNotification::query()
            ->where('notification_digest_id', $digestId)
            ->where('organization_id', $organizationId)
            ->whereIn('notifiable_id', $actionIds)
            ->with([
                'notifiable.alertHighlights',
                'notifiable.process.subjects',
                'notifiable.process.organizations' => fn ($q) => $q->where('organizations.id', $organizationId),
            ])
            ->get()
            ->each(function (OrganizationNotification $notif) use (&$lookup): void {
                $action = $notif->notifiable;
                if ($action instanceof ProcessAction) {
                    $lookup[$action->id] = $notif;
                }
            });

        return $lookup;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawItems
     * @param  array<string, OrganizationNotification|array<int, OrganizationNotification>>  $notifLookup
     * @return array<int, array<string, mixed>|null>
     */
    private static function formatRawItems(
        array $rawItems,
        array $notifLookup,
        string $organizationId,
        ?NotificationDigestFilterData $filters,
    ): array {
        return array_map(function (array $item) use ($notifLookup, $organizationId, $filters): ?array {
            if ($filters instanceof NotificationDigestFilterData && ! self::shouldIncludeItem($item, $filters)) {
                return null;
            }

            $item['_sort_timestamp'] = self::calculateSortTimestamp($item['registration_date'] ?? null);
            $item = self::applyKeyMappings($item);
            $item = self::enrichItemData($item, $notifLookup, $organizationId);
            $item = self::applyTitleCaseFormatting($item);

            return self::applyFinalDateFormatting($item);
        }, $rawItems);
    }

    /**
     * @param  array<int, array<string, mixed>>  $finalData
     */
    private static function buildResource(NotificationDigest $digest, array $finalData): self
    {
        $notified = $digest->email_sent_at !== null || $digest->whatsapp_sent_at !== null || $digest->sms_sent_at !== null;

        return new self(
            id: $digest->id,
            data: $finalData,
            date: DateFormatHelper::formatDate($digest->created_at),
            time: $digest->created_at->format('g:ia'),
            period: DateFormatHelper::getPeriodFromHour((int) $digest->created_at->format('H')),
            actions_count: count($finalData),
            is_notified: $notified,
            email_notified_at: $digest->email_sent_at ? DateFormatHelper::formatDateWithTime($digest->email_sent_at) : null,
            whatsapp_notified_at: $digest->whatsapp_sent_at ? DateFormatHelper::formatDateWithTime($digest->whatsapp_sent_at) : null,
            sms_notified_at: $digest->sms_sent_at ? DateFormatHelper::formatDateWithTime($digest->sms_sent_at) : null,
        );
    }

    private static function buildNotificationLookup(NotificationDigest $digest): array
    {
        $notifLookup = [];
        foreach ($digest->notifications as $notif) {
            $action = $notif->notifiable;
            if ($action instanceof ProcessAction) {
                $notifLookup[$action->id] = $notif;
            }
        }

        return $notifLookup;
    }

    private static function shouldIncludeItem(array $item, NotificationDigestFilterData $filters): bool
    {
        // Filter by process number
        if ($filters->process_number && isset($item['process_number']) && ! str_contains((string) $item['process_number'], $filters->process_number)) {
            return false;
        }

        // Filter by dates
        if (! self::checkDateFilter($item['registration_date'] ?? null, $filters->registration_date_from, $filters->registration_date_to)) {
            return false;
        }

        if (! self::checkDateFilter($item['action_date'] ?? null, $filters->action_date_from, $filters->action_date_to)) {
            return false;
        }

        if (! self::checkDateFilter($item['term_start_date'] ?? $item['start_date'] ?? null, $filters->term_start_date_from, $filters->term_start_date_to)) {
            return false;
        }

        return self::checkDateFilter($item['term_end_date'] ?? $item['end_date'] ?? null, $filters->term_end_date_from, $filters->term_end_date_to);
    }

    private static function checkDateFilter(?string $value, ?string $from, ?string $to): bool
    {
        if (! $from && ! $to) {
            return true;
        }

        if (! $value) {
            return false;
        }

        try {
            $date = str_contains($value, '/') ? Date::createFromFormat('d/m/Y', $value) : Date::parse($value);
            if ($from && $date->lt(Date::parse($from)->startOfDay())) {
                return false;
            }

            if ($to && $date->gt(Date::parse($to)->endOfDay())) {
                return false;
            }
        } catch (\Exception) {
            return false;
        }

        return true;
    }

    private static function calculateSortTimestamp(?string $rawRegDate): int
    {
        if (! $rawRegDate) {
            return 0;
        }

        try {
            return str_contains($rawRegDate, '/')
                ? Date::createFromFormat('d/m/Y', $rawRegDate)->timestamp
                : Date::parse($rawRegDate)->timestamp;
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

    /**
     * @param  array<string, OrganizationNotification>  $notifLookup
     * @param  array<string, string>  $processIdsByNumber
     * @param  \Illuminate\Support\Collection<string, Process>|array<string, Process>  $processesById
     */
    private static function enrichItemData(
        array $item,
        array $notifLookup,
        string $organizationId,
        array $processIdsByNumber = [],
        \Illuminate\Support\Collection|array $processesById = [],
    ): array {
        /** @var OrganizationNotification|null $matchedNotif */
        $matchedNotif = null;
        $actionId = $item['process_action_id'] ?? null;
        $liveProcess = null;

        if (is_string($actionId) && $actionId !== '' && isset($notifLookup[$actionId])) {
            $matchedNotif = $notifLookup[$actionId];
        }

        // 1. Alert Highlights
        if ($matchedNotif && (empty($item['alert_highlights']))) {
            $actionModel = $matchedNotif->notifiable;
            if ($actionModel instanceof ProcessAction) {
                $item['alert_highlights'] = $actionModel->alertHighlights
                    ->unique(fn ($h): string => "{$h->start}-{$h->end}-{$h->detected_text}-{$h->source}")
                    ->map(fn ($h): array => [
                        'start' => $h->start,
                        'end' => $h->end,
                        'text' => $h->detected_text,
                        'source' => $h->source,
                    ])->values()->all();
            }
        }

        $item['alert_highlights'] ??= [];

        // 2. Alert Level
        if ($matchedNotif) {
            $actionModel = $matchedNotif->notifiable;
            if ($actionModel instanceof ProcessAction) {
                $process = $actionModel->process;
                $liveProcess = $process;
                $item['process_id'] = $process->id;
                $orgProc = $process->organizations->firstWhere('id', $matchedNotif->organization_id);
                $role = self::parseLawyerRole($orgProc?->pivot->lawyer_role);
                $item['alert_level'] = self::resolveAlertLevelForProcess($process, $organizationId, $orgProc?->pivot->inactivity_alert_level, $role);
                $item['lawyer_role'] = $role instanceof ProcessLawyerRole ? $role->getLabel() : (string) ($orgProc?->pivot->lawyer_role ?? '');

                // Inyectamos el ID de la actuación y el consecutivo para poder relacionar actuaciones
                $item['process_action_id'] = $actionModel->id;
                $item['cons_action'] = $actionModel->cons_action;
            }
        } else {
            $item['process_id'] ??= null;
            $item['alert_level'] ??= null;
            $item['lawyer_role'] ??= null;
            $item['cons_action'] ??= 0;

            // Fallback: Si no hay match exacto por actuación pero tenemos radicado,
            // intentamos recuperar el process_id buscando el proceso en la organización
            if (empty($item['process_id']) && ! empty($item['process_number'])) {
                $processNumber = (string) $item['process_number'];
                if (isset($processIdsByNumber[$processNumber])) {
                    $item['process_id'] = $processIdsByNumber[$processNumber];
                } else {
                    $process = \Src\Domain\Process\Models\Process::query()
                        ->where('process_number', $processNumber)
                        ->value('id');

                    if ($process) {
                        $item['process_id'] = $process;
                    }
                }
            }

            // Si la actuación es residual (ya no existe en la base de datos de notificaciones),
            // le inyectamos un ID sintético basado en su hash para permitir que las validaciones de emparejamiento sigan funcionando
            if (empty($item['process_action_id']) && empty($item['id'])) {
                $item['process_action_id'] = 'synth-'.md5(($item['process_number'] ?? '').($item['action_text'] ?? '').($item['annotation'] ?? ''));
            }

            if (! empty($item['process_id']) && empty($item['alert_level'])) {
                $process = $processesById[$item['process_id']] ?? null;

                if (! $process instanceof Process) {
                    $process = Process::query()
                        ->with(['organizations' => fn ($q) => $q->where('organizations.id', $organizationId)])
                        ->find($item['process_id']);
                }

                if ($process instanceof Process) {
                    $liveProcess = $process;
                    $orgProc = $process->relationLoaded('organizations')
                        ? $process->organizations->firstWhere('id', $organizationId)
                        : $process->organizations()->where('organizations.id', $organizationId)->first();
                    $role = self::parseLawyerRole($orgProc?->pivot->lawyer_role);
                    $item['alert_level'] = self::resolveAlertLevelForProcess($process, $organizationId, $orgProc?->pivot->inactivity_alert_level, $role);
                    $item['lawyer_role'] ??= $role instanceof ProcessLawyerRole ? $role->getLabel() : (string) ($orgProc?->pivot->lawyer_role ?? '');
                }
            }
        }

        // Refresh parties from live process when digest snapshot was empty (subjects added later by admin).
        $item = self::hydrateSubjectsFromLiveProcess($item, $liveProcess, $processesById);

        // 3. Subjects (Title Case & Pluralization)
        $subjects = ['plaintiff' => 'plaintiffs', 'defendant' => 'defendants'];
        foreach ($subjects as $singular => $plural) {
            if (isset($item[$singular]) && is_string($item[$singular])) {
                $list = array_values(array_filter(
                    array_map(trim(...), explode(',', $item[$singular])),
                    static fn (string $name): bool => $name !== '' && $name !== '---',
                ));
                if ($list !== []) {
                    $list = array_map(StrParseHelper::toTitleCase(...), $list);
                    $item[$plural] = $list;
                    $count = count($list);
                    $first = $list[0];
                    $item[$singular] = $count > 1 ? "{$first} (+".($count - 1).')' : $first;
                } else {
                    $item[$plural] = [];
                    $item[$singular] = '---';
                }
            } else {
                $item[$plural] ??= [];
                $item[$singular] ??= '---';
            }
        }

        return $item;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Process>|array<string, Process>  $processesById
     */
    private static function hydrateSubjectsFromLiveProcess(
        array $item,
        ?Process $liveProcess,
        \Illuminate\Support\Collection|array $processesById = [],
    ): array {
        if (! self::needsSubjectHydration($item)) {
            return $item;
        }

        $process = $liveProcess;

        if (! $process instanceof Process && ! empty($item['process_id'])) {
            $process = $processesById[$item['process_id']] ?? null;

            if (! $process instanceof Process) {
                $process = Process::query()->with('subjects')->find($item['process_id']);
            }
        }

        if (! $process instanceof Process && ! empty($item['process_number'])) {
            $process = Process::query()
                ->with('subjects')
                ->where('process_number', $item['process_number'])
                ->first();
        }

        if (! $process instanceof Process) {
            return $item;
        }

        if (! $process->relationLoaded('subjects')) {
            $process->load('subjects');
        }

        $summary = ProcessSubjectSummaryHelper::summarize($process->subjects);

        if ($summary['plaintiffs'] !== []) {
            $item['plaintiff'] = implode(', ', $summary['plaintiffs']);
        }

        if ($summary['defendants'] !== []) {
            $item['defendant'] = implode(', ', $summary['defendants']);
        }

        return $item;
    }

    private static function needsSubjectHydration(array $item): bool
    {
        // Keys are already mapped to plaintiff/defendant when this runs.
        if (self::isBlankPartyValue($item['plaintiff'] ?? null)) {
            return true;
        }

        return self::isBlankPartyValue($item['defendant'] ?? null);
    }

    private static function isBlankPartyValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return true;
        }

        $trimmed = trim($value);

        return $trimmed === '' || $trimmed === '---';
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
                } catch (\Exception) {
                }
            }
        }

        return $item;
    }

    private static function parseLawyerRole(mixed $role): ?ProcessLawyerRole
    {
        if ($role instanceof ProcessLawyerRole) {
            return $role;
        }

        return is_string($role) ? ProcessLawyerRole::tryFrom($role) : null;
    }

    private static function resolveAlertLevelForProcess(
        Process $process,
        string $organizationId,
        ?string $storedAlertLevel,
        ?ProcessLawyerRole $lawyerRole,
    ): ?string {
        if (! $lawyerRole instanceof \Src\Domain\Process\Enums\ProcessLawyerRole) {
            $organization = $process->relationLoaded('organizations')
                ? $process->organizations->firstWhere('id', $organizationId)
                : $process->organizations()->where('organizations.id', $organizationId)->first();

            $lawyerRole = self::parseLawyerRole($organization?->pivot->lawyer_role);
            $storedAlertLevel ??= $organization?->pivot->inactivity_alert_level;
        }

        return ProcessAlertLevelHelper::resolve(
            $storedAlertLevel,
            $process->last_activity_date,
            $lawyerRole,
        );
    }

    private static function processFinalCollection(array $formattedData): array
    {
        // Filter out nulls
        $collection = array_values(array_filter($formattedData));

        // Sort descending
        usort($collection, fn (array $a, array $b): int => $b['_sort_timestamp'] <=> $a['_sort_timestamp']);

        // Strip sorting metadata
        return array_map(function (array $item): array {
            unset($item['_sort_timestamp']);

            return $item;
        }, $collection);
    }
}
