<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

final class CourtDirectoryReportFake
{
    public const RESOURCE_KEY = '29ee3c4f-6632-4f53-820f-7149cef34a66';

    /**
     * @param  list<array{email: string, name: string, corporation: string, specialty: string, department: string, city: string, account_type: string, desk_code: string}>  $rows
     */
    public static function fake(array $rows, int $queryStatus = 200): void
    {
        app()->forgetInstance(Factory::class);
        Http::clearResolvedInstances();

        $token = 'eyJrIjoiMjllZTNjNGYtNjYzMi00ZjUzLTgyMGYtNzE0OWNlZjM0YTY2IiwidCI6IjYyMmNiYTk4LTgwZjgtNDFmMy04ZGY1LThlYjk5OTAxNTk4YiIsImMiOjR9';

        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($rows, $queryStatus, $token) {
            $url = $request->url();

            if (str_contains($url, 'ramajudicial.gov.co')) {
                return Http::response('<iframe src="https://app.powerbi.com/view?r='.$token.'"></iframe>');
            }

            if (str_contains($url, 'app.powerbi.com/view')) {
                return Http::response("var resolvedClusterUri = 'https://wabi-test-redirect.analysis.windows.net/';");
            }

            if (str_contains($url, 'modelsAndExploration')) {
                return Http::response(self::model());
            }

            if (str_contains($url, 'querydata')) {
                return Http::response(self::query($rows), $queryStatus);
            }

            return Http::response('unexpected '.$url, 500);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function model(): array
    {
        $properties = [
            'EMAIL',
            'NOMBRE',
            'EMPRESA',
            'DEPARTAMENTO',
            'Estado Provincia',
            'CIUDAD',
            'TIPO DE CUENTA',
            'CODIGO DE DESPACHO',
        ];

        return [
            'models' => [[
                'id' => 953077,
                'dbName' => 'dataset-id',
            ]],
            'exploration' => [
                'report' => ['objectId' => 'report-object'],
                'sections' => [[
                    'visualContainers' => [[
                        'config' => json_encode([
                            'singleVisual' => [
                                'visualType' => 'tableEx',
                                'prototypeQuery' => [
                                    'Version' => 2,
                                    'From' => [[
                                        'Name' => 'c',
                                        'Entity' => 'Correos',
                                        'Type' => 0,
                                    ]],
                                    'Select' => array_map(
                                        fn (string $property): array => [
                                            'Column' => [
                                                'Expression' => ['SourceRef' => ['Source' => 'c']],
                                                'Property' => $property,
                                            ],
                                            'Name' => 'Correos.'.$property,
                                        ],
                                        $properties,
                                    ),
                                ],
                            ],
                        ]),
                    ]],
                ]],
            ],
        ];
    }

    /**
     * @param  list<array{email: string, name: string, corporation: string, specialty: string, department: string, city: string, account_type: string, desk_code: string}>  $rows
     * @return array<string, mixed>
     */
    private static function query(array $rows): array
    {
        $keys = ['email', 'name', 'corporation', 'specialty', 'department', 'city', 'account_type', 'desk_code'];
        $dictionaries = array_fill(0, count($keys), []);
        $dataRows = [];

        foreach ($rows as $row) {
            $indexes = [];
            foreach ($keys as $column => $key) {
                $dictionaries[$column][] = $row[$key];
                $indexes[] = count($dictionaries[$column]) - 1;
            }

            $dataRows[] = ['C' => $indexes];
        }

        $valueDicts = [];
        foreach ($dictionaries as $column => $values) {
            $valueDicts['D'.$column] = $values;
        }

        return [
            'results' => [[
                'result' => [
                    'data' => [
                        'dsr' => [
                            'DS' => [[
                                'ValueDicts' => $valueDicts,
                                'PH' => [[
                                    'DM0' => $dataRows,
                                ]],
                            ]],
                        ],
                    ],
                ],
            ]],
        ];
    }
}
