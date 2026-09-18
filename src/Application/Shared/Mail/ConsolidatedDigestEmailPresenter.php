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
        $origin = $this->resolveFrontendOrigin();
        $path = trim((string) config('notification.mail.frontend_digest_path', '/actuaciones-recientes'), '/');

        if ($path === '') {
            $path = 'actuaciones-recientes';
        }

        return $origin.'/'.$path.'?digest='.rawurlencode($digestId);
    }

    private function resolveFrontendOrigin(): string
    {
        $candidate = (string) (config('notification.mail.frontend_url_email_consolidated')
            ?: config('app.frontend_url')
            ?: config('tasks.frontend.base_url', 'http://localhost:4200'));

        $candidate = rtrim($candidate, '/');
        $parts = parse_url($candidate);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return $candidate;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
