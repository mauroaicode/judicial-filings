<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Src\Application\Shared\CourtDirectory\CourtDirectoryHttpClient;

it('sends directory requests through the residential proxy when it is enabled', function (): void {
    config([
        'judicial-branch.proxy.enabled' => true,
        'judicial-branch.proxy.protocol' => 'http',
        'judicial-branch.proxy.host' => 'rp.example.test',
        'judicial-branch.proxy.port' => 6060,
        'judicial-branch.proxy.username' => 'user',
        'judicial-branch.proxy.password' => 'secret',
        'judicial-branch.proxy.enable_session_mutation' => false,
    ]);

    $proxy = null;
    Http::fake(function ($request, array $options) use (&$proxy) {
        $proxy = $options['proxy'] ?? null;

        return Http::response('ok');
    });

    app(CourtDirectoryHttpClient::class)->get('https://www.ramajudicial.gov.co/directorio-cuentas-de-correo-electronico');

    expect($proxy)->toBe('http://user:secret@rp.example.test:6060');
});

it('leaves directory requests direct when the residential proxy is disabled', function (): void {
    config(['judicial-branch.proxy.enabled' => false]);

    $proxy = 'unset';
    Http::fake(function ($request, array $options) use (&$proxy) {
        $proxy = $options['proxy'] ?? null;

        return Http::response('ok');
    });

    app(CourtDirectoryHttpClient::class)->get('https://www.ramajudicial.gov.co/directorio-cuentas-de-correo-electronico');

    expect($proxy)->toBeNull();
});
