<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Process;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Throwable;

/**
 * Repara procesos SAMAI importados de forma incompleta:
 *  - sin despacho/clase
 *  - solo con la última página de actuaciones (historial incompleto)
 *  - anotaciones truncadas del grid HTML (... )
 */
class BackfillIncompleteSamaiProcessesService
{
    public function __construct(
        private readonly ProcessSyncService $processSyncService,
    ) {}

    /**
     * @return array{
     *     scanned: int,
     *     repaired: int,
     *     failed: int,
     *     skipped: int,
     *     actions_added: int,
     *     metadata_updated: int,
     *     subjects_added: int,
     *     failures: list<array{process_number: string, process_id: string, error: string}>
     * }
     */
    public function handle(
        ?string $radicado = null,
        ?string $organizationId = null,
        bool $onlyIncomplete = true,
        bool $dryRun = false,
        bool $notify = false,
    ): array {
        $channel = config('judicial-sync.log_channel', 'judicial_sync_notifications');
        $processes = $this->queryCandidates($radicado, $organizationId, $onlyIncomplete);

        $summary = [
            'scanned' => $processes->count(),
            'repaired' => 0,
            'failed' => 0,
            'skipped' => 0,
            'actions_added' => 0,
            'metadata_updated' => 0,
            'subjects_added' => 0,
            'failures' => [],
        ];

        Log::channel($channel)->info('BackfillIncompleteSamaiProcessesService started', [
            'radicado' => $radicado,
            'organization_id' => $organizationId,
            'only_incomplete' => $onlyIncomplete,
            'dry_run' => $dryRun,
            'notify' => $notify,
            'scanned' => $summary['scanned'],
        ]);

        foreach ($processes as $process) {
            if ($dryRun) {
                $summary['skipped']++;

                continue;
            }

            try {
                $result = $this->processSyncService->backfillSamaiProcess($process, $notify);
                $summary['repaired']++;
                $summary['actions_added'] += $result['actions_added'];
                $summary['subjects_added'] += $result['subjects_added'];

                if ($result['metadata_updated']) {
                    $summary['metadata_updated']++;
                }
            } catch (Throwable $exception) {
                $summary['failed']++;
                $summary['failures'][] = [
                    'process_number' => (string) $process->process_number,
                    'process_id' => (string) $process->id,
                    'error' => $exception->getMessage(),
                ];

                Log::channel($channel)->error('BackfillIncompleteSamaiProcessesService failed', [
                    'process_id' => $process->id,
                    'process_number' => $process->process_number,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::channel($channel)->info('BackfillIncompleteSamaiProcessesService finished', $summary);

        return $summary;
    }

    /**
     * @return Collection<int, Process>
     */
    public function queryCandidates(
        ?string $radicado = null,
        ?string $organizationId = null,
        bool $onlyIncomplete = true,
    ): Collection {
        $query = Process::query()
            ->whereNotNull('samai_corporacion')
            ->where('samai_corporacion', '!=', '')
            ->where('is_manual_sync', false)
            ->whereHas(
                'processDataSource',
                fn (Builder $q) => $q->where('slug', ProcessDataSourceSlug::Samai->value)
            );

        if ($radicado !== null && $radicado !== '') {
            $query->where('process_number', $radicado);
        } elseif ($onlyIncomplete) {
            $query->where(function (Builder $q): void {
                $q->whereNull('court')
                    ->orWhere('court', '')
                    ->orWhereNull('process_class')
                    ->orWhere('process_class', '')
                    // Despacho genérico sin número (ej. "Juzgado Administrativo - Ibague").
                    ->orWhere(function (Builder $courtQ): void {
                        $courtQ->where(function (Builder $pattern): void {
                            $pattern->where('court', 'like', 'Juzgado Administrativo -%')
                                ->orWhere('court', 'like', 'Juzgado Administrativo de %')
                                ->orWhere('court', 'like', 'Tribunal Administrativo -%');
                        })->where('court', 'not regexp', '[0-9]{2,3}');
                    })
                    ->orWhereHas(
                        'actions',
                        fn (Builder $actions): Builder => $actions->where('annotation', 'like', '%...')
                    )
                    ->orWhereRaw(
                        '(select coalesce(min(action_registration_id), 0) from process_actions where process_id = processes.id) > 1'
                    )
                    ->orWhereRaw(
                        '(select coalesce(max(action_registration_id), 0) from process_actions where process_id = processes.id)
                         > (select count(*) from process_actions where process_id = processes.id)'
                    );
            });
        }

        if ($organizationId !== null && $organizationId !== '') {
            $query->whereHas(
                'organizations',
                fn (Builder $q) => $q->where('organizations.id', $organizationId)
            );
        }

        return $query->orderBy('process_number')->get();
    }
}
