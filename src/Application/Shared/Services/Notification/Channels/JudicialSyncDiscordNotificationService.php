<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Carbon\CarbonInterface;
use Illuminate\Bus\Batch;
use Src\Domain\JudicialSync\Enums\JudicialSyncDataSource;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

/**
 * Builds human-readable Discord reports for {@see JudicialSyncRun} lifecycle outcomes.
 */
readonly class JudicialSyncDiscordNotificationService
{
    /** Discord embed field values are capped at 1024 characters; longer payloads are rejected silently by the API. */
    private const DISCORD_EMBED_FIELD_MAX_CHARS = 1024;

    public function __construct(
        private DiscordNotificationChannelService $discordChannel,
        private StaleReplicationAlertCollector $staleReplicationCollector,
    ) {}

    public function notifyNoProcesses(JudicialSyncRun $run): void
    {
        if (! $this->discordChannel->canSend(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY)) {
            return;
        }

        $embed = $this->baseEmbed($run, 'Sin procesos a sincronizar', 'No hay radicados elegibles con suscripción activa para este ciclo.', '#95A5A6');

        $this->discordChannel->send(
            DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY,
            '**'.$this->syncTitle($run).'** — ciclo finalizado sin trabajo pendiente.',
            [$embed]
        );
    }

    public function notifyDispatchFailed(JudicialSyncRun $run): void
    {
        if (! $this->discordChannel->canSend(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY)) {
            return;
        }

        $error = $run->dispatch_error ?? 'Error desconocido al encolar el batch.';
        $embed = $this->baseEmbed($run, 'Fallo al encolar la sincronización', $error, '#E74C3C');

        $this->discordChannel->send(
            DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY,
            '**'.$this->syncTitle($run).'** — **error crítico** al despachar el batch.',
            [$embed]
        );
    }

    public function notifyBatchFinished(JudicialSyncRun $run, Batch $batch): void
    {
        if (! $this->discordChannel->canSend(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY)) {
            return;
        }

        $title = 'Sincronización completada';
        $color = '#27AE60';
        $summaryLine = 'Todos los jobs del batch finalizaron correctamente.';

        if ($batch->cancelledAt !== null) {
            $title = 'Batch cancelado';
            $color = '#7F8C8D';
            $summaryLine = 'El batch fue cancelado antes de completar todos los jobs.';
        } elseif ($batch->failedJobs > 0) {
            $title = 'Sincronización completada con fallos';
            $color = '#F39C12';
            $summaryLine = 'El batch terminó; algunos jobs fallaron (revisar logs / failed_jobs).';
        }

        $succeeded = max(0, $batch->totalJobs - $batch->failedJobs);
        $failedStored = (string) ($run->failed_jobs_count ?? $batch->failedJobs);

        $embed = [
            'title' => $title,
            'description' => $summaryLine."\n\n".'_Informe enviado al cerrar la cola; las cifras son el resultado final._',
            'color' => $color,
            'fields' => [
                $this->field('Fuente', $this->dataSourceLabel($run), true),
                $this->field(
                    'Cronología',
                    $this->timelineBlockForBatchRun($run),
                    false
                ),
                $this->field('Jobs en batch', (string) $batch->totalJobs, true),
                $this->field('Completaron bien', (string) $succeeded, true),
                $this->field('Fallaron', (string) $batch->failedJobs, true),
                $this->field(
                    'Registro del ciclo (BD)',
                    'Radicados encolados en este run · **'.$run->processes_queued."**\n"
                    .'Fallos guardados tras el batch · **'.$failedStored.'**',
                    false
                ),
                $this->field(
                    'Referencias',
                    $this->technicalRefsBlock($run),
                    false
                ),
            ],
            'footer' => [
                'text' => 'Run '.$run->id.' · '.$run->status->value.' · '.$this->dataSourceSlug($run),
            ],
        ];

        $this->discordChannel->send(
            DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY,
            '**'.$this->syncTitle($run).'** — '.$title,
            [$embed]
        );

        $this->notifyStaleReplicationIfNeeded($run);
    }

    /**
     * Alerts #sincronizacion-tardia when Rama detail reports replication older than the threshold.
     * Only applies to Rama Judicial batches (SAMAI has no equivalent field).
     */
    public function notifyStaleReplicationIfNeeded(JudicialSyncRun $run): void
    {
        if ($this->resolveDataSource($run) !== JudicialSyncDataSource::JudicialBranch) {
            return;
        }

        if (! $this->discordChannel->canSend(DiscordNotificationChannelService::CHANNEL_LATE_SYNC)) {
            $this->staleReplicationCollector->pullAll();

            return;
        }

        $items = $this->staleReplicationCollector->pullAll();
        if ($items === []) {
            return;
        }

        $thresholdHours = max(1, (int) config('judicial-sync.replication_staleness.stale_after_hours', 24));
        $excludeWeekends = (bool) config('judicial-sync.replication_staleness.exclude_weekends', true);
        $excludeHolidays = (bool) config('judicial-sync.replication_staleness.exclude_colombia_holidays', true);
        $hourUnitLabel = $this->lateSyncHourUnitLabel($excludeWeekends, $excludeHolidays);
        $lagNote = $this->lateSyncLagNote($excludeWeekends, $excludeHolidays);
        $chunks = $this->chunkLateSyncDetalleLines($items, $excludeWeekends || $excludeHolidays);
        $totalChunks = count($chunks);

        foreach ($chunks as $index => $detalle) {
            $part = $index + 1;
            $isFirst = $index === 0;

            $fields = [];
            if ($isFirst) {
                $fields[] = $this->field('Radicados afectados', (string) count($items), true);
                $fields[] = $this->field('Umbral', $thresholdHours.' '.$hourUnitLabel, true);
                $fields[] = $this->field('Ciclo sync', '`'.$run->id.'`', true);
            } else {
                $fields[] = $this->field('Ciclo sync', '`'.$run->id.'`', true);
                $fields[] = $this->field('Parte', $part.'/'.$totalChunks, true);
            }

            $fields[] = $this->field(
                $totalChunks > 1 ? 'Radicados ('.$part.'/'.$totalChunks.')' : 'Radicados',
                $detalle,
                false
            );

            $embed = [
                'title' => $isFirst
                    ? 'Replicación de datos atrasada en Rama'
                    : 'Replicación atrasada (continuación '.$part.'/'.$totalChunks.')',
                'description' => $isFirst
                    ? 'La API de detalle reportó `ultimaActualizacion` (fecha de replicación) '
                        .'con más de **'.$thresholdHours.' '.$hourUnitLabel.'** de diferencia frente a `fechaConsulta`'
                        .$lagNote
                        ."\n\nRevisar manualmente estos radicados para no perder actuaciones/notificaciones."
                        ."\n_Lista compacta: radicado · atraso. Detalle fino en logs `StaleReplicationDetector`._"
                    : 'Continuación del listado de radicados con replicación atrasada.',
                'color' => '#E67E22',
                'fields' => $fields,
                'footer' => [
                    'text' => 'Canal sincronización tardía · run '.$run->id
                        .($totalChunks > 1 ? ' · '.$part.'/'.$totalChunks : ''),
                ],
            ];

            $content = $isFirst
                ? '**Sincronización tardía** — '.count($items).' radicado(s) con replicación atrasada en Rama.'
                : '**Sincronización tardía** — continuación '.$part.'/'.$totalChunks.'.';

            $this->discordChannel->send(
                DiscordNotificationChannelService::CHANNEL_LATE_SYNC,
                $content,
                [$embed]
            );
        }
    }

    private function lateSyncHourUnitLabel(bool $excludeWeekends, bool $excludeHolidays): string
    {
        if ($excludeWeekends && $excludeHolidays) {
            return 'h hábiles (lun–vie, sin festivos CO)';
        }

        if ($excludeWeekends) {
            return 'h hábiles (lun–vie)';
        }

        if ($excludeHolidays) {
            return 'h (sin festivos CO)';
        }

        return 'h';
    }

    private function lateSyncLagNote(bool $excludeWeekends, bool $excludeHolidays): string
    {
        if ($excludeWeekends && $excludeHolidays) {
            return ' (sábado, domingo y festivos de Colombia no cuentan).';
        }

        if ($excludeWeekends) {
            return ' (sábado y domingo no cuentan).';
        }

        if ($excludeHolidays) {
            return ' (festivos de Colombia no cuentan).';
        }

        return '.';
    }

    /**
     * Compact one-liners packed into Discord-safe field chunks (≤1024 chars each).
     *
     * @param  list<array{process_number: string, consulted_at: string, replicated_at: string, lag_hours: int, court: string|null}>  $items
     * @return list<string>
     */
    private function chunkLateSyncDetalleLines(array $items, bool $useBusinessHoursLabel = true): array
    {
        $suffix = $useBusinessHoursLabel ? 'h hábiles' : 'h';
        $lines = [];
        foreach ($items as $item) {
            $lines[] = '`'.$item['process_number'].'` · **'.$item['lag_hours'].$suffix.'**';
        }

        if ($lines === []) {
            return [];
        }

        $chunks = [];
        $current = [];

        foreach ($lines as $line) {
            $candidate = $current === [] ? $line : implode("\n", $current)."\n".$line;
            if (strlen($candidate) > self::DISCORD_EMBED_FIELD_MAX_CHARS && $current !== []) {
                $chunks[] = implode("\n", $current);
                $current = [$line];

                continue;
            }

            if (strlen($candidate) > self::DISCORD_EMBED_FIELD_MAX_CHARS) {
                $chunks[] = mb_substr($line, 0, self::DISCORD_EMBED_FIELD_MAX_CHARS - 1).'…';
                $current = [];

                continue;
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $chunks[] = implode("\n", $current);
        }

        return $chunks;
    }

    private function timelineBlockForBatchRun(JudicialSyncRun $run): string
    {
        $tzLines = [];

        $line = $this->formatTzLine('Inicio', $run->started_at);
        if ($line !== null) {
            $tzLines[] = $line;
        }

        $line = $this->formatTzLine('Fin encolamiento (comando)', $run->command_finished_at);
        if ($line !== null) {
            $tzLines[] = $line;
            $enqueueNote = $this->briefElapsed($run->started_at, $run->command_finished_at);
            if ($enqueueNote !== null) {
                $tzLines[] = '└ Duración hasta encolar: **'.$enqueueNote.'**';
            }
        }

        $line = $this->formatTzLine('Fin cola de trabajos', $run->batch_finished_at);
        if ($line !== null) {
            $tzLines[] = $line;
            $queueNote = $this->briefElapsed($run->command_finished_at, $run->batch_finished_at)
                ?? $this->briefElapsed($run->started_at, $run->batch_finished_at);
            if ($queueNote !== null) {
                $tzLines[] = '└ Trabajo en cola · **'.$queueNote.'**';
            }
        }

        $filtro = trim((string) ($run->radicado_filter ?? ''));
        $tzLines[] = $filtro === '' ? '**Filtro radicado:** _Todos los elegibles_' : '**Filtro radicado:** `'.$filtro.'`';

        return implode("\n", $tzLines);
    }

    private function technicalRefsBlock(JudicialSyncRun $run): string
    {
        $chunks = [];

        if ($run->laravel_batch_id !== null) {
            $chunks[] = '**Batch Laravel** `'.$run->laravel_batch_id.'`';
        }

        if ($run->command_exit_code !== null) {
            $chunks[] = '**Código salida comando** '.$run->command_exit_code;
        }

        return $chunks !== [] ? implode("\n", $chunks) : '—';
    }

    private function formatTzLine(string $label, ?CarbonInterface $moment): ?string
    {
        if (! $moment instanceof \Carbon\CarbonInterface) {
            return null;
        }

        return '**'.$label.'** '.$moment->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s T');
    }

    private function briefElapsed(?CarbonInterface $from, ?CarbonInterface $until): ?string
    {
        if (! $from instanceof \Carbon\CarbonInterface || ! $until instanceof \Carbon\CarbonInterface) {
            return null;
        }

        $seconds = (int) abs($from->diffInSeconds($until));

        return $seconds < 60 ? $seconds.' s' : (int) round($seconds / 60).' min';
    }

    /**
     * @return array<string, mixed>
     */
    private function baseEmbed(JudicialSyncRun $run, string $title, string $description, string $colorHex): array
    {
        $lines = [
            '**Fuente** '.$this->dataSourceLabel($run),
            '**Inicio** '.$run->started_at->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s T'),
        ];

        if ($run->command_finished_at !== null) {
            $lines[] = '**Fin comando** '.$run->command_finished_at->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s T');
        }

        if ($run->batch_finished_at !== null) {
            $lines[] = '**Fin batch** '.$run->batch_finished_at->timezone((string) config('app.timezone'))->format('Y-m-d H:i:s T');
        }

        $filtroRaw = trim((string) ($run->radicado_filter ?? ''));
        $lines[] = $filtroRaw === '' ? '**Filtro radicado** _Todos los elegibles_' : '**Filtro radicado** `'.$filtroRaw.'`';

        $lines[] = '**Ejecución** `'.$run->id.'`';
        $lines[] = '**Estado** `'.$run->status->value.'`';

        if ($run->laravel_batch_id !== null) {
            $lines[] = '**Batch Laravel** `'.$run->laravel_batch_id.'`';
        }

        if ($run->command_exit_code !== null) {
            $lines[] = '**Código salida comando** '.$run->command_exit_code;
        }

        $body = implode("\n", $lines);

        return [
            'title' => $title,
            'description' => $description."\n\n".$body,
            'color' => $colorHex,
        ];
    }

    /**
     * @return array{name: string, value: string, inline: bool}
     */
    private function field(string $name, string $value, bool $inline): array
    {
        if (strlen($value) > self::DISCORD_EMBED_FIELD_MAX_CHARS) {
            $value = mb_substr($value, 0, self::DISCORD_EMBED_FIELD_MAX_CHARS - 1).'…';
        }

        return [
            'name' => $name,
            'value' => $value,
            'inline' => $inline,
        ];
    }

    private function syncTitle(JudicialSyncRun $run): string
    {
        return match ($this->resolveDataSource($run)) {
            JudicialSyncDataSource::Samai => 'Sincronización SAMAI',
            JudicialSyncDataSource::Tyba => 'Sincronización TYBA',
            default => 'Sincronización Rama Judicial',
        };
    }

    private function dataSourceLabel(JudicialSyncRun $run): string
    {
        return $this->resolveDataSource($run)->getLabel();
    }

    private function dataSourceSlug(JudicialSyncRun $run): string
    {
        return $this->resolveDataSource($run)->value;
    }

    private function resolveDataSource(JudicialSyncRun $run): JudicialSyncDataSource
    {
        return $run->data_source;
    }
}
