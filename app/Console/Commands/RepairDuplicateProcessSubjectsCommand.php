<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Src\Application\Shared\Helpers\ProcessSubjectIdentityHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

/**
 * Removes duplicate procedural-subject links caused by different idRegSujeto values
 * per judicial instance for the same person within a radicado.
 */
class RepairDuplicateProcessSubjectsCommand extends Command
{
    protected $signature = 'judicial:repair-duplicate-subjects
                            {--radicado= : Radicado (process_number) to repair}
                            {--process= : Single process UUID instead of full radicado}
                            {--all : Repair every radicado with duplicate subject links}
                            {--force : Skip confirmation when using --all without --dry-run}
                            {--dry-run : Preview changes without writing}';

    protected $description = 'Remove duplicate sujetos procesales linked to the same process instance (multi-instance radicados).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $radicado = $this->option('radicado');
        $processUuid = $this->option('process');
        $all = (bool) $this->option('all');

        $filters = [
            ($radicado !== null && $radicado !== ''),
            ($processUuid !== null && $processUuid !== ''),
            $all,
        ];

        if (count(array_filter($filters)) !== 1) {
            $this->error('Provide exactly one of --radicado=, --process=, or --all.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written to the database.');
        }

        $radicados = $this->resolveRadicados($radicado, $processUuid, $all);

        if ($radicados === []) {
            $this->warn('No processes found for the given filter.');

            return self::SUCCESS;
        }

        if ($all && ! $dryRun && ! (bool) $this->option('force')) {
            $count = count($radicados);
            if (! $this->confirm("Repair {$count} radicado(s) with duplicate subject links?", false)) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        if ($all) {
            $this->info('Scanning '.count($radicados).' radicado(s)...');
        }

        $totalDetached = 0;
        $totalAttached = 0;
        $affectedRadicados = 0;

        foreach ($radicados as $processNumber) {
            $processes = Process::query()
                ->where('process_number', $processNumber)
                ->orderBy('created_at')
                ->get();

            if ($processes->isEmpty()) {
                continue;
            }

            $result = $this->repairRadicado($processes, $dryRun);

            if ($result['detached'] === 0 && $result['attached'] === 0) {
                continue;
            }

            $affectedRadicados++;
            $totalDetached += $result['detached'];
            $totalAttached += $result['attached'];

            if (! $all) {
                $this->info("Radicado {$processNumber}: detached {$result['detached']}, attached {$result['attached']} subject link(s).");
            }
        }

        if ($all && $affectedRadicados > 0) {
            $this->info("Repaired {$affectedRadicados} radicado(s).");
        }

        $this->newLine();
        $this->info("Affected radicados: {$affectedRadicados}");
        $this->info("Total links detached: {$totalDetached}");
        $this->info("Total links attached: {$totalAttached}");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveRadicados(?string $radicado, ?string $processUuid, bool $all): array
    {
        if ($all) {
            return $this->radicadosWithDuplicateSubjectLinks();
        }

        if ($processUuid !== null && $processUuid !== '') {
            $processNumber = Process::query()->where('id', $processUuid)->value('process_number');

            return is_string($processNumber) && $processNumber !== '' ? [$processNumber] : [];
        }

        return is_string($radicado) && $radicado !== '' ? [$radicado] : [];
    }

    /**
     * @return list<string>
     */
    private function radicadosWithDuplicateSubjectLinks(): array
    {
        return Process::query()
            ->whereIn('id', function ($query): void {
                $query->select('pps.process_id')
                    ->from('process_process_subject as pps')
                    ->join('process_subjects as ps', 'ps.id', '=', 'pps.process_subject_id')
                    ->groupBy('pps.process_id', 'ps.subject_type', 'ps.name_or_business_name', 'ps.identification')
                    ->havingRaw('COUNT(*) > 1');
            })
            ->distinct()
            ->orderBy('process_number')
            ->pluck('process_number')
            ->map(fn ($value): string => (string) $value)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Process>  $processes
     * @return array{detached: int, attached: int}
     */
    private function repairRadicado(Collection $processes, bool $dryRun): array
    {
        /** @var array<string, ProcessSubject> $canonicalByIdentity */
        $canonicalByIdentity = [];

        foreach ($processes as $process) {
            foreach ($process->subjects as $subject) {
                $identityKey = ProcessSubjectIdentityHelper::key($subject);

                if (! isset($canonicalByIdentity[$identityKey])) {
                    $canonicalByIdentity[$identityKey] = $subject;

                    continue;
                }

                $canonicalByIdentity[$identityKey] = ProcessSubjectIdentityHelper::pickCanonical(
                    collect([$canonicalByIdentity[$identityKey], $subject]),
                );
            }
        }

        if ($canonicalByIdentity === []) {
            return ['detached' => 0, 'attached' => 0];
        }

        $canonicalIds = collect($canonicalByIdentity)
            ->map(fn (ProcessSubject $subject): string => (string) $subject->id)
            ->values()
            ->all();

        $detached = 0;
        $attached = 0;

        foreach ($processes as $process) {
            $subjects = $process->subjects()->get();
            $detachIds = [];

            foreach ($subjects as $subject) {
                $identityKey = ProcessSubjectIdentityHelper::key($subject);
                $canonicalId = (string) $canonicalByIdentity[$identityKey]->id;

                if ((string) $subject->id !== $canonicalId) {
                    $detachIds[] = $subject->id;
                }
            }

            if ($detachIds !== []) {
                $detached += count($detachIds);

                if (! $dryRun) {
                    $process->subjects()->detach($detachIds);
                }
            }

            $existing = $process->subjects()->pluck('process_subjects.id')->map(fn ($value): string => (string) $value)->all();
            $missing = array_diff($canonicalIds, $existing);

            if ($missing !== []) {
                $attached += count($missing);

                if (! $dryRun) {
                    $process->subjects()->syncWithoutDetaching($missing);
                }
            }
        }

        return ['detached' => $detached, 'attached' => $attached];
    }
}
