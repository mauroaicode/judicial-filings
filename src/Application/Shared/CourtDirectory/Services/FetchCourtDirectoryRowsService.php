<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Src\Application\Shared\CourtDirectory\CourtDirectoryHttpClient;
use Src\Application\Shared\CourtDirectory\CourtDirectorySyncException;
use Src\Application\Shared\CourtDirectory\DecodePowerBiDataShape;
use Src\Application\Shared\CourtDirectory\ResolvedCourtDirectoryReport;
use Src\Domain\CourtDirectory\CourtDirectoryRow;

readonly class FetchCourtDirectoryRowsService
{
    public function __construct(
        private ResolveCourtDirectoryReportService $resolveCourtDirectoryReportService,
        private DecodePowerBiDataShape $decodePowerBiDataShape,
        private CourtDirectoryHttpClient $courtDirectoryHttpClient,
    ) {}

    /**
     * @return list<CourtDirectoryRow>
     */
    public function handle(): array
    {
        $report = $this->resolveCourtDirectoryReportService->handle();
        $model = $this->getJson(
            $report,
            $report->apiBaseUrl.'/public/reports/'.$report->resourceKey.'/modelsAndExploration?preferReadOnlySession=true',
        );

        $prototypeQuery = $this->prototypeQuery($model);
        $properties = $this->selectProperties($prototypeQuery);
        $modelId = $model['models'][0]['id'] ?? null;
        $datasetId = $model['models'][0]['dbName'] ?? null;
        $reportObjectId = $model['exploration']['report']['objectId'] ?? null;

        if (! is_int($modelId) || ! is_string($datasetId) || $datasetId === '' || ! is_string($reportObjectId) || $reportObjectId === '') {
            throw new CourtDirectorySyncException('El reporte de Power BI no incluye la tabla de correos.');
        }

        $query = $this->postJson(
            $report,
            $report->apiBaseUrl.'/public/reports/querydata?synchronous=true',
            $this->queryBody($prototypeQuery, $properties, $modelId, $datasetId, $reportObjectId),
        );

        return $this->decodePowerBiDataShape->decode($query, $properties);
    }

    /**
     * @param  array<string, mixed>  $model
     * @return array<string, mixed>
     */
    private function prototypeQuery(array $model): array
    {
        $sections = $model['exploration']['sections'] ?? null;
        if (! is_array($sections)) {
            throw new CourtDirectorySyncException('El reporte de Power BI no incluye la tabla de correos.');
        }

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            foreach ($section['visualContainers'] ?? [] as $container) {
                if (! is_array($container)) {
                    continue;
                }

                $config = $this->jsonObject($container['config'] ?? null);
                $query = $config['singleVisual']['prototypeQuery'] ?? null;
                $visualType = $config['singleVisual']['visualType'] ?? null;
                if ($visualType !== 'tableEx') {
                    continue;
                }

                if (! is_array($query)) {
                    continue;
                }

                $properties = $this->selectProperties($query);
                if (in_array('EMAIL', $properties, true) && in_array('CODIGO DE DESPACHO', $properties, true)) {
                    return $query;
                }
            }
        }

        throw new CourtDirectorySyncException('El reporte de Power BI no incluye la tabla de correos.');
    }

    /**
     * @param  array<string, mixed>  $prototypeQuery
     * @return list<string>
     */
    private function selectProperties(array $prototypeQuery): array
    {
        $select = $prototypeQuery['Select'] ?? null;
        if (! is_array($select)) {
            throw new CourtDirectorySyncException('El reporte de Power BI no incluye la tabla de correos.');
        }

        $properties = [];
        foreach ($select as $column) {
            $property = is_array($column) ? ($column['Column']['Property'] ?? null) : null;
            if (! is_string($property) || $property === '') {
                throw new CourtDirectorySyncException('El reporte de Power BI no incluye la tabla de correos.');
            }

            $properties[] = $property;
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $prototypeQuery
     * @param  list<string>  $properties
     * @return array<string, mixed>
     */
    private function queryBody(array $prototypeQuery, array $properties, int $modelId, string $datasetId, string $reportObjectId): array
    {
        return [
            'version' => '1.0.0',
            'queries' => [[
                'Query' => [
                    'Commands' => [[
                        'SemanticQueryDataShapeCommand' => [
                            'Query' => $prototypeQuery,
                            'Binding' => [
                                'Primary' => [
                                    'Groupings' => [[
                                        'Projections' => range(0, count($properties) - 1),
                                    ]],
                                ],
                                'DataReduction' => [
                                    'DataVolume' => 4,
                                    'Primary' => [
                                        'Window' => ['Count' => (int) config('court-directory.window_count')],
                                    ],
                                ],
                                'Version' => 1,
                            ],
                            'ExecutionMetricsKind' => 1,
                        ],
                    ]],
                ],
                'QueryId' => '',
                'ApplicationContext' => [
                    'DatasetId' => $datasetId,
                    'Sources' => [[
                        'ReportId' => $reportObjectId,
                    ]],
                ],
            ]],
            'cancelQueries' => [],
            'modelId' => $modelId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getJson(ResolvedCourtDirectoryReport $report, string $url): array
    {
        return $this->json($this->courtDirectoryHttpClient->get($url, $this->headers($report)));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function postJson(ResolvedCourtDirectoryReport $report, string $url, array $body): array
    {
        return $this->json($this->courtDirectoryHttpClient->post($url, $body, $this->headers($report)));
    }

    /**
     * @return array<string, string>
     */
    private function headers(ResolvedCourtDirectoryReport $report): array
    {
        $requestId = (string) Str::uuid();

        return [
            'Accept' => 'application/json',
            'ActivityId' => $requestId,
            'RequestId' => $requestId,
            'X-PowerBI-ResourceKey' => $report->resourceKey,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        if ($response->failed()) {
            throw new CourtDirectorySyncException("La consulta del directorio respondió {$response->status()}.");
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new CourtDirectorySyncException('La consulta del directorio no devolvió JSON.');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
