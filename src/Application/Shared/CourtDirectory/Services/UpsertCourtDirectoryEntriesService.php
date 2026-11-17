<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Src\Application\Shared\CourtDirectory\CourtDirectorySyncException;
use Src\Application\Shared\CourtDirectory\CourtDirectoryUpsertResult;
use Src\Domain\CourtDirectory\CourtDirectoryRow;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;

readonly class UpsertCourtDirectoryEntriesService
{
    /**
     * @param  list<CourtDirectoryRow>  $rows
     */
    public function handle(CourtDirectorySyncRun $run, array $rows): CourtDirectoryUpsertResult
    {
        $uniqueRows = $this->uniqueByEmail($rows);
        if ($uniqueRows === []) {
            throw new CourtDirectorySyncException('El directorio de correos no devolvió filas.');
        }

        $seenAt = now();
        foreach (array_chunk($uniqueRows, (int) config('court-directory.upsert_chunk')) as $chunk) {
            CourtDirectoryEntry::query()->upsert(
                array_map(fn (CourtDirectoryRow $row): array => $this->payload($row, $run->id, $seenAt), $chunk),
                ['email'],
                [
                    'name',
                    'corporation',
                    'specialty',
                    'department',
                    'city',
                    'account_type',
                    'desk_code',
                    'is_active',
                    'last_seen_at',
                    'missing_at',
                    'last_sync_run_id',
                    'updated_at',
                ],
            );
        }

        $markedInactive = CourtDirectoryEntry::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($run): void {
                $query->whereNull('last_sync_run_id')
                    ->orWhere('last_sync_run_id', '!=', $run->id);
            })
            ->update([
                'is_active' => false,
                'missing_at' => $seenAt,
                'last_sync_run_id' => $run->id,
                'updated_at' => $seenAt,
            ]);

        return new CourtDirectoryUpsertResult(
            fetched: count($rows),
            upserted: count($uniqueRows),
            markedInactive: $markedInactive,
        );
    }

    /**
     * @param  list<CourtDirectoryRow>  $rows
     * @return list<CourtDirectoryRow>
     */
    private function uniqueByEmail(array $rows): array
    {
        $unique = [];
        foreach ($rows as $row) {
            $unique[$row->email] = $row;
        }

        return array_values($unique);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CourtDirectoryRow $row, string $runId, Carbon $seenAt): array
    {
        return [
            'id' => (string) Str::uuid(),
            'email' => $row->email,
            'name' => $row->name,
            'corporation' => $row->corporation,
            'specialty' => $row->specialty,
            'department' => $row->department,
            'city' => $row->city,
            'account_type' => $row->accountType,
            'desk_code' => $row->deskCode,
            'is_active' => true,
            'last_seen_at' => $seenAt,
            'missing_at' => null,
            'last_sync_run_id' => $runId,
            'created_at' => $seenAt,
            'updated_at' => $seenAt,
        ];
    }
}
