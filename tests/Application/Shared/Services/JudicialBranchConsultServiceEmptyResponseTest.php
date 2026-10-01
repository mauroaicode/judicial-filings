<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Src\Application\Shared\Exceptions\ApiEmptyProcessesException;
use Src\Application\Shared\Exceptions\ApiProxyFailureException;
use Src\Application\Shared\Services\JudicialBranchConsultService;

beforeEach(function (): void {
    Sleep::fake();
    config([
        'judicial-branch.api_url' => 'https://consultaprocesos.ramajudicial.gov.co:448/api/v2',
        'judicial-branch.proxy.enabled' => false,
        'judicial-branch.proxy.max_connection_retries' => 0,
        'judicial-branch.proxy.call_delay_min_ms' => 0,
        'judicial-branch.proxy.call_delay_max_ms' => 0,
    ]);
});

it('retries a 502 as a proxy failure instead of treating the radicado as missing', function (): void {
    Http::fake([
        '*' => Http::response(
            '<html>502 Bad Gateway</html>',
            502,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $service = app(JudicialBranchConsultService::class);

    expect(fn () => $service->fetchProcesses('76001310500220170063501'))
        ->toThrow(ApiProxyFailureException::class);
});

it('retries a 200 body that is not a process list', function (): void {
    Http::fake([
        '*' => Http::response(
            '<html>ok</html>',
            200,
            ['Content-Type' => 'text/plain'],
        ),
    ]);

    $service = app(JudicialBranchConsultService::class);

    expect(fn () => $service->fetchProcesses('76001310500220170063501'))
        ->toThrow(ApiProxyFailureException::class);
});

it('still treats a real empty process list as not found', function (): void {
    Http::fake([
        '*' => Http::response([
            'procesos' => [],
            'paginacion' => ['cantidadPaginas' => 1],
        ], 200),
    ]);

    $service = app(JudicialBranchConsultService::class);

    expect(fn () => $service->fetchProcesses('76001310500220170063501'))
        ->toThrow(ApiEmptyProcessesException::class);
});
