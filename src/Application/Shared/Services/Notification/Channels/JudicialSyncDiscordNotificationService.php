<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Carbon\CarbonInterface;
use Illuminate\Bus\Batch;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

/**
 * Builds human-readable Discord reports for {@see JudicialSyncRun} lifecycle outcomes.
 */
readonly class JudicialSyncDiscordNotificationService
{
    public function __construct(
        private DiscordNotificationChannelService $discordChannel,
    ) {}

    public function notifyNoProcesses(JudicialSyncRun $run): void
    {
        if (! $this->discordChannel->canSend(DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY)) {
            return;
        }

        $embed = $this->baseEmbed($run, 'Sin procesos a sincronizar', 'No hay radicados elegibles con suscripción activa para este ciclo.', '#95A5A6');

        $this->discordChannel->send(
            DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY,
            '**Sincronización Rama Judicial** — ciclo finalizado sin trabajo pendiente.',
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
            '**Sincronización Rama Judicial** — **error crítico** al despachar el batch.',
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
                'text' => 'Run '.$run->id.' · '.$run->status->value,
            ],
        ];

        $this->discordChannel->send(
            DiscordNotificationChannelService::CHANNEL_LOG_SYNC_DAILY,
            $this->batchCompletionHeadline($title),
            [$embed]
        );
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
        return [
            'name' => $name,
            'value' => $value,
            'inline' => $inline,
        ];
    }

    private function batchCompletionHeadline(string $title): string
    {
        return '**Sincronización Rama Judicial** — '.$title;
    }
}
