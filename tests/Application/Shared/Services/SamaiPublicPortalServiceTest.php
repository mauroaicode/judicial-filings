<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Application\Shared\Services\SamaiPublicPortalService;

it('extracts actuaciones and subjects from the public SAMAI portal', function (): void {
    config()->set('samai.public_portal.url', 'https://samai.test');
    config()->set('samai.public_portal.max_attempts', 1);

    Http::fakeSequence()
        ->push(<<<'HTML'
            <html><body><form>
                <input type="hidden" name="__VIEWSTATE" value="initial-state">
                <input type="hidden" name="__EVENTVALIDATION" value="initial-validation">
                <span id="MainContent_Lbldato1">63</span>
                <span id="MainContent_Lbldato2">36</span>
                <span id="MainContent_Lbldato3">T Q</span>
            </form></body></html>
            HTML)
        ->push(<<<'HTML'
            <html><body><form>
                <input type="hidden" name="__VIEWSTATE" value="process-state">
                <input type="hidden" name="__EVENTVALIDATION" value="process-validation">
                <table id="MainContent_GridViewHistoricoActuaciones">
                    <tr><th>Ver</th><th>Fecha registro</th><th>Fecha actuación</th><th>Actuación</th><th>Anotación</th><th>Estado</th><th>Anexos</th><th>Índice</th></tr>
                    <tr><td></td><td>11/01/2024 17:01:42</td><td>12/01/2024</td><td>Fijacion estado</td><td>CVC-</td><td>REGISTRADA</td><td>0</td><td>00011</td></tr>
                </table>
            </form></body></html>
            HTML)
        ->push(<<<'HTML'
            <html><body>
                <table id="MainContent_GVsujetos">
                    <tr><th>Reg</th><th>Tipo de sujeto</th><th>Nombre</th><th>Acceso Web</th></tr>
                    <tr><td>1</td><td>Demandante/accionante</td><td>Manuel Millán</td><td>NO</td></tr>
                </table>
                <table id="MainContent_GridViewHistoricoActuaciones">
                    <tr><th>Ver</th><th>Fecha registro</th><th>Fecha actuación</th><th>Actuación</th><th>Anotación</th><th>Estado</th><th>Anexos</th><th>Índice</th></tr>
                    <tr><td></td><td>11/01/2024 17:01:42</td><td>12/01/2024</td><td>Fijacion estado</td><td>CVC-</td><td>REGISTRADA</td><td>0</td><td>00011</td></tr>
                </table>
            </body></html>
            HTML);

    $result = app(SamaiPublicPortalService::class)
        ->fetch('76111333300220180006700', '7611133');

    expect($result['actuaciones'])->toHaveCount(1)
        ->and($result['actuaciones'][0])->toMatchArray([
            'Orden' => 11,
            'Actuacion' => '12/01/2024',
            'NombreActuacion' => 'Fijacion estado',
            'Anotacion' => 'CVC-',
            'Registro' => '11/01/2024 17:01:42',
            'Estado' => 'REGISTRADA',
            'Anexos' => 0,
        ])
        ->and($result['sujetos'])->toHaveCount(1)
        ->and($result['sujetos'][0])->toMatchArray([
            'TipoSujeto' => 'Demandante/accionante',
            'NombreRazonSocial' => 'Manuel Millán',
            'Identificacion' => null,
            'EsEmplazado' => false,
        ]);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => str_contains($request->body(), '6336TQ'));
    Http::assertSent(fn ($request): bool => str_contains(
        urldecode($request->body()),
        '__EVENTTARGET=ctl00$MainContent$LbtSujetos'
    ));
});

it('reuses one public portal session for actuaciones and subjects', function (): void {
    config()->set('samai.api_key', '');
    config()->set('samai.public_portal.enabled', true);

    $portal = Mockery::mock(SamaiPublicPortalService::class);
    $portal->shouldReceive('fetch')
        ->once()
        ->with('76111333300220180006700', '7611133')
        ->andReturn([
            'actuaciones' => [['Orden' => 11]],
            'sujetos' => [['NombreRazonSocial' => 'Manuel Millán']],
        ]);

    $service = new SamaiConsultService($portal);

    $actuaciones = $service->obtenerActuaciones('7611133', '76111333300220180006700');
    $sujetos = $service->obtenerSujetosProcesales('7611133', '76111333300220180006700');

    expect($actuaciones->isSuccessful)->toBeTrue()
        ->and($actuaciones->data)->toHaveCount(1)
        ->and($sujetos->isSuccessful)->toBeTrue()
        ->and($sujetos->data)->toHaveCount(1);
});
