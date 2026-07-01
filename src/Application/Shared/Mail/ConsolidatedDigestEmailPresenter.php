<?php

declare(strict_types=1);

namespace Src\Application\Shared\Mail;

use Illuminate\Support\Collection;

final class ConsolidatedDigestEmailPresenter
{
    /**
     * @return array{
     *     displayedRows: Collection<int, array<string, mixed>>,
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
        $maxRows = (int) config('notification.mail.digest_max_rows', 0);

        $totalActionsCount = $sorted->count();
        $displayed = $maxRows > 0 ? $sorted->take($maxRows) : $sorted;
        $remainingActionsCount = $maxRows > 0 ? max(0, $totalActionsCount - $displayed->count()) : 0;

        return [
            'displayedRows' => $displayed,
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

    private function resolveDigestUrl(string $digestId): string
    {
        $base = (string) (config('notification.mail.frontend_url_email_consolidated')
            ?: config('tasks.frontend.base_url', 'http://localhost:4200'));

        $base = rtrim($base, '/');

        $path = trim((string) config('notification.mail.frontend_digest_path', '/notification-digests'), '/');

        return "{$base}/{$path}/{$digestId}";
    }
}
