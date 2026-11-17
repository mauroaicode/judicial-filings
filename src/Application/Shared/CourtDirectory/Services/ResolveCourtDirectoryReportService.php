<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Illuminate\Http\Client\Response;
use Src\Application\Shared\CourtDirectory\CourtDirectoryHttpClient;
use Src\Application\Shared\CourtDirectory\CourtDirectorySyncException;
use Src\Application\Shared\CourtDirectory\ResolvedCourtDirectoryReport;

readonly class ResolveCourtDirectoryReportService
{
    public function __construct(
        private CourtDirectoryHttpClient $courtDirectoryHttpClient,
    ) {}

    public function handle(): ResolvedCourtDirectoryReport
    {
        $page = $this->get((string) config('court-directory.source_url'));
        if (preg_match('/https:\/\/app\.powerbi\.com\/view\?r=([A-Za-z0-9_-]+)/', $page->body(), $iframe) !== 1) {
            throw new CourtDirectorySyncException('No se encontró el iframe del directorio de correos en la página de la Rama Judicial.');
        }

        $resourceKey = $this->resourceKey($iframe[1]);
        $embed = $this->get('https://app.powerbi.com/view?r='.$iframe[1]);
        if (preg_match("/resolvedClusterUri\\s*=\\s*'([^']+)'/", $embed->body(), $cluster) !== 1) {
            throw new CourtDirectorySyncException('No se encontró el clúster del reporte de Power BI.');
        }

        return new ResolvedCourtDirectoryReport(
            resourceKey: $resourceKey,
            apiBaseUrl: $this->apiBaseUrl($cluster[1]),
        );
    }

    private function resourceKey(string $token): string
    {
        $padding = (4 - strlen($token) % 4) % 4;
        $decoded = base64_decode($token.str_repeat('=', $padding), true);
        $payload = is_string($decoded) ? json_decode($decoded, true) : null;
        $resourceKey = is_array($payload) ? ($payload['k'] ?? null) : null;

        if (! is_string($resourceKey) || preg_match('/^[0-9a-f-]{36}$/i', $resourceKey) !== 1) {
            throw new CourtDirectorySyncException('El parámetro del reporte de Power BI no es válido.');
        }

        return $resourceKey;
    }

    private function apiBaseUrl(string $clusterUri): string
    {
        $host = parse_url($clusterUri, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            throw new CourtDirectorySyncException('No se encontró el clúster del reporte de Power BI.');
        }

        $parts = explode('.', $host);
        $parts[0] = str_replace('global-', '', str_replace('-redirect', '', $parts[0])).'-api';

        return 'https://'.implode('.', $parts);
    }

    private function get(string $url): Response
    {
        $response = $this->courtDirectoryHttpClient->get($url, [
            'Accept' => 'text/html',
        ]);

        if ($response->failed()) {
            throw new CourtDirectorySyncException("La consulta del directorio respondió {$response->status()}.");
        }

        return $response;
    }
}
