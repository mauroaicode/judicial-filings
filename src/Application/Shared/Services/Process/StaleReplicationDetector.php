<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Application\Shared\Services\Notification\Channels\StaleReplicationAlertCollector;
use Src\Domain\Process\Models\Process;
use Throwable;

/**
 * Detects Rama Judicial "fecha de replicación de datos" lag using detail API fields
 * {@see fechaConsulta} and {@see ultimaActualizacion}.
 */
final readonly class StaleReplicationDetector
{
    public function __construct(
        private JudicialBranchConsultService $judicialService,
        private StaleReplicationAlertCollector $collector,
        private ColombiaHolidayCalendar $colombiaHolidays,
    ) {}

    public function evaluateRadicado(string $processNumber, Process $process): void
    {
        if (! (bool) config('judicial-sync.replication_staleness.enabled', true)) {
            return;
        }

        $apiProcessId = (int) ($process->process_id ?? 0);
        if ($apiProcessId === 0) {
            return;
        }

        try {
            $detail = $this->judicialService->fetchDetailProcess($apiProcessId);
        } catch (Throwable $e) {
            Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
                ->warning('StaleReplicationDetector: detail fetch failed', [
                    'process_number' => $processNumber,
                    'message' => $e->getMessage(),
                ]);

            return;
        }

        if (! $detail->isSuccessful) {
            return;
        }

        $this->evaluateDetailPayload($processNumber, $detail->data, $process->court);
    }

    /**
     * @param  array<string, mixed>  $detailData
     */
    public function evaluateDetailPayload(string $processNumber, array $detailData, ?string $court = null): void
    {
        if (! (bool) config('judicial-sync.replication_staleness.enabled', true)) {
            return;
        }

        $consultedAt = $this->parseDateTime($detailData['fechaConsulta'] ?? null);
        $replicatedAt = $this->parseDateTime($detailData['ultimaActualizacion'] ?? null);

        if (! $consultedAt instanceof \Illuminate\Support\Carbon || ! $replicatedAt instanceof \Illuminate\Support\Carbon) {
            return;
        }

        $thresholdHours = max(1, (int) config('judicial-sync.replication_staleness.stale_after_hours', 24));
        $excludeWeekends = (bool) config('judicial-sync.replication_staleness.exclude_weekends', true);
        $excludeHolidays = (bool) config('judicial-sync.replication_staleness.exclude_colombia_holidays', true);
        $lagHours = $this->lagHours($replicatedAt, $consultedAt, $excludeWeekends, $excludeHolidays);

        if ($lagHours < $thresholdHours) {
            return;
        }

        $this->collector->remember([
            'process_number' => $processNumber,
            'consulted_at' => $consultedAt->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s T'),
            'replicated_at' => $replicatedAt->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s T'),
            'lag_hours' => $lagHours,
            'court' => $court !== null && trim($court) !== '' ? trim($court) : null,
        ]);

        Log::channel(config('judicial-sync.log_channel', 'judicial_sync_notifications'))
            ->warning('StaleReplicationDetector: stale Rama data replication', [
                'process_number' => $processNumber,
                'consulted_at' => $consultedAt->toIso8601String(),
                'replicated_at' => $replicatedAt->toIso8601String(),
                'lag_hours' => $lagHours,
                'threshold_hours' => $thresholdHours,
                'exclude_weekends' => $excludeWeekends,
                'exclude_colombia_holidays' => $excludeHolidays,
            ]);
    }

    /**
     * Hours between replication and consultation. Optionally skips Saturday/Sunday and
     * Colombian public holidays (package {@see ColombiaHolidayCalendar}).
     */
    private function lagHours(
        Carbon $replicatedAt,
        Carbon $consultedAt,
        bool $excludeWeekends,
        bool $excludeHolidays,
    ): int {
        if (! $excludeWeekends && ! $excludeHolidays) {
            return (int) $replicatedAt->diffInHours($consultedAt);
        }

        /** @var array<string, true> $holidayDates */
        $holidayDates = $excludeHolidays
            ? $this->colombiaHolidays->holidayDatesBetween($replicatedAt, $consultedAt)
            : [];

        return $replicatedAt->diffInHoursFiltered(
            static function (Carbon $hour) use ($excludeWeekends, $holidayDates): bool {
                if ($excludeWeekends && $hour->isWeekend()) {
                    return false;
                }

                if ($holidayDates !== [] && isset($holidayDates[$hour->toDateString()])) {
                    return false;
                }

                return true;
            },
            $consultedAt,
        );
    }

    private function parseDateTime(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Date::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
