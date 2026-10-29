<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Notification\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Src\Application\AppUser\Notification\Resources\NotificationDigestResource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\ProcessNumberFormatHelper;
use Src\Application\Shared\Mail\ConsolidatedDigestEmailPresenter;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Process\Services\GroupProcessActionsService;
use Symfony\Component\HttpFoundation\Response;

class ExportNotificationDigestPdfService
{
    public function __construct(
        private readonly GroupProcessActionsService $groupProcessActionsService,
        private readonly ConsolidatedDigestEmailPresenter $digestEmailPresenter,
    ) {}

    public function handle(string $organizationId, string $digestId): Response
    {
        $digest = $this->findDigest($organizationId, $digestId);
        $viewData = $this->buildViewData($digest);

        $filename = $this->buildFilename($digest, $viewData['date']);

        return Pdf::loadView('pdfs.notification-digest', $viewData)
            ->setPaper('letter', 'landscape')
            ->download($filename);
    }

    private function findDigest(string $organizationId, string $digestId): NotificationDigest
    {
        /** @var NotificationDigest $digest */
        $digest = NotificationDigest::query()
            ->with('organization')
            ->whereOrganization($organizationId)
            ->where('id', $digestId)
            ->firstOrFail();

        return $digest;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildViewData(NotificationDigest $digest): array
    {
        $resource = NotificationDigestResource::fromModel($digest)->toArray();
        $rows = is_array($resource['data'] ?? null) ? $resource['data'] : [];

        if ($rows !== []) {
            $rows = $this->groupProcessActionsService->handle(collect($rows))->values()->all();
            $rows = $this->collapseClaimedAutos($rows);
            $rows = array_map($this->prepareRowForPdf(...), $rows);
        }

        $alertsCount = collect($rows)->where('is_alert', true)->count();
        $processesCount = collect($rows)->pluck('process_number')->filter()->unique()->count();

        return [
            'logoDataUri' => $this->resolveLogoDataUri(),
            'organizationName' => $digest->organization->name ?? '—',
            'date' => $resource['date'] ?? DateFormatHelper::formatDate($digest->created_at),
            'time' => $resource['time'] ?? $digest->created_at->format('g:ia'),
            'period' => $resource['period'] ?? DateFormatHelper::getPeriodFromHour((int) $digest->created_at->format('H')),
            'actionsCount' => count($rows),
            'processesCount' => $processesCount,
            'alertsCount' => $alertsCount,
            'isNotified' => (bool) ($resource['is_notified'] ?? false),
            'emailNotifiedAt' => $resource['email_notified_at'] ?? null,
            'whatsappNotifiedAt' => $resource['whatsapp_notified_at'] ?? null,
            'smsNotifiedAt' => $resource['sms_notified_at'] ?? null,
            'rows' => $rows,
            'digestUrl' => $this->digestEmailPresenter->digestUrl($digest->id),
            'generatedAt' => DateFormatHelper::formatDateWithTime(now()),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function prepareRowForPdf(array $row): array
    {
        $row['process_number_formatted'] = ProcessNumberFormatHelper::display(
            isset($row['process_number']) && is_string($row['process_number']) ? $row['process_number'] : null,
            '—',
        );
        $row['plaintiff_names'] = $this->resolveSubjectNames($row, 'plaintiffs', 'plaintiff', 'demandante');
        $row['defendant_names'] = $this->resolveSubjectNames($row, 'defendants', 'defendant', 'demandado');

        return $row;
    }

    /**
     * Prefer the full subject list over the truncated singular summary ("Name (+2)").
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function resolveSubjectNames(array $row, string $listKey, string $singularKey, string $legacyKey): array
    {
        $list = $row[$listKey] ?? null;

        if (is_array($list) && $list !== []) {
            return array_values(array_filter(
                array_map(static function (mixed $name): string {
                    if (! is_string($name)) {
                        return '';
                    }

                    $trimmed = trim($name);

                    return ($trimmed === '' || $trimmed === '---') ? '' : $trimmed;
                }, $list),
                static fn (string $name): bool => $name !== '',
            ));
        }

        $fallback = $row[$singularKey] ?? $row[$legacyKey] ?? null;

        if (! is_string($fallback)) {
            return [];
        }

        $trimmed = trim($fallback);

        if ($trimmed === '' || $trimmed === '---') {
            return [];
        }

        // Singular summary like "Foo (+2)" is incomplete — avoid showing it as the only name.
        if (preg_match('/\(\+\d+\)$/', $trimmed) === 1) {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), explode(',', $trimmed)),
            static fn (string $name): bool => $name !== '' && $name !== '---',
        ));
    }

    /**
     * Drop Auto rows already claimed by a merged Fijación so the PDF shows one row per pair.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function collapseClaimedAutos(array $rows): array
    {
        $claimedAutoIds = collect($rows)
            ->filter(static fn (array $row): bool => ! empty($row['is_merged']) && ! empty($row['notified_action_id']))
            ->pluck('notified_action_id')
            ->filter()
            ->all();

        if ($claimedAutoIds === []) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static function (array $row) use ($claimedAutoIds): bool {
                $actionId = $row['process_action_id'] ?? $row['id'] ?? null;

                if (! is_string($actionId) || $actionId === '') {
                    return true;
                }

                return ! in_array($actionId, $claimedAutoIds, true);
            },
        ));
    }

    private function resolveLogoDataUri(): ?string
    {
        $path = public_path('images/logo-notijudicial-grande.png');

        if (! is_file($path)) {
            $path = public_path('images/logo-notijudicial.png');
        }

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($contents);
    }

    private function buildFilename(NotificationDigest $digest, string $dateLabel): string
    {
        $dateSlug = Str::slug($dateLabel) ?: $digest->created_at->format('Y-m-d');

        return "consolidado-{$dateSlug}.pdf";
    }
}
