<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Support\Collection;

final class ConsolidatedDigestEmailPresenter
{
    /**
     * @return array{
     *     processGroups: Collection<int, array{
     *         process_number: string,
     *         court: string,
     *         demandante: string,
     *         demandado: string,
     *         has_alert: bool,
     *         actions: list<array<string, mixed>>
     *     }>,
     *     totalActionsCount: int,
     *     totalProcessesCount: int,
     *     alertsCount: int,
     *     displayedActionsCount: int,
     *     remainingActionsCount: int,
     *     digestUrl: string
     * }
     */
    public function present(Collection $data, string $digestId): array
    {
        $sorted = $this->sortForEmail($data);
        $maxRows = max(1, (int) config('notification.mail.digest_max_rows', 8));

        $totalActionsCount = $sorted->count();
        $displayed = $sorted->take($maxRows);
        $remainingActionsCount = max(0, $totalActionsCount - $displayed->count());

        $processGroups = $this->groupByProcess($displayed);

        return [
            'processGroups' => $processGroups,
            'totalActionsCount' => $totalActionsCount,
            'totalProcessesCount' => $sorted->unique('process_number')->count(),
            'alertsCount' => $sorted->where('is_alert', true)->count(),
            'displayedActionsCount' => $displayed->count(),
            'remainingActionsCount' => $remainingActionsCount,
            'digestUrl' => $this->resolveDigestUrl($digestId),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $data
     * @return Collection<int, array<string, mixed>>
     */
    private function sortForEmail(Collection $data): Collection
    {
        return $data->values()->sort(function (array $a, array $b): int {
            $aAlert = (bool) ($a['is_alert'] ?? false);
            $bAlert = (bool) ($b['is_alert'] ?? false);

            if ($aAlert !== $bAlert) {
                return $bAlert <=> $aAlert;
            }

            return 0;
        })->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $displayed
     * @return Collection<int, array{
     *     process_number: string,
     *     court: string,
     *     demandante: string,
     *     demandado: string,
     *     has_alert: bool,
     *     actions: list<array<string, mixed>>
     * }>
     */
    private function groupByProcess(Collection $displayed): Collection
    {
        return $displayed
            ->groupBy('process_number')
            ->map(function (Collection $actions, string $processNumber): array {
                /** @var array<string, mixed> $first */
                $first = $actions->first();

                return [
                    'process_number' => $processNumber,
                    'court' => (string) ($first['court'] ?? ''),
                    'demandante' => (string) ($first['demandante'] ?? '---'),
                    'demandado' => (string) ($first['demandado'] ?? '---'),
                    'has_alert' => $actions->contains(fn (array $row): bool => (bool) ($row['is_alert'] ?? false)),
                    'actions' => array_values($actions->values()->all()),
                ];
            })
            ->values();
    }

    private function resolveDigestUrl(string $digestId): string
    {
        $base = (string) (config('notification.mail.frontend_url_email_consolidated')
            ?: config('tasks.frontend.base_url', 'http://localhost:4200'));

        $base = rtrim($base, '/');

        $path = trim((string) config('notification.mail.frontend_digest_path', '/notification-digests'), '/');

        return "{$base}/{$path}/{$digestId}";
    }
}
