<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Services\SamaiConsultService;
use Src\Application\Shared\Services\SamaiPublicPortalService;

it('extracts actuaciones, subjects and metadata from the public SAMAI portal', function (): void {
    config()->set('samai.public_portal.url', 'https://samai.test');
    config()->set('samai.public_portal.max_attempts', 1);
    config()->set('samai.public_portal.expand_truncated_annotations', false);

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
                <span id="MainContent_LblPonente">JUZGADO 14 ADMINISTRATIVO DE CALI</span>
                <span id="MainContent_lblclase1">ACCION DE REPARACION DIRECTA</span>
                <span id="MainContent_LblNombreCorporacion">Juzgado Administrativo de Cali -</span>
                <span id="MainContent_LblorigenNombre">Juzgado Administrativo 013 JUZGADO ADMINISTRATIVO DE CALI (VALLE)</span>
                <span id="MainContent_LblEVigente">SI</span>
                <span id="MainContent_Lblfecharad">29/02/2016 0:00:00</span>
                <span id="MainContent_LblTotalRegistros">Total registros: 2</span>
                <span id="MainContent_lblpagini">2</span>
                <span id="MainContent_Lblpagfin">2</span>
                <input type="checkbox" name="ctl00$MainContent$ChkVerTodasActuaciones" id="MainContent_ChkVerTodasActuaciones">
                <table id="MainContent_GridViewHistoricoActuaciones">
                    <tr><th>Ver</th><th>Fecha registro</th><th>Fecha actuación</th><th>Actuación</th><th>Anotación</th><th>Estado</th><th>Anexos</th><th>Índice</th></tr>
                    <tr><td></td><td>11/01/2024 17:01:42</td><td>12/01/2024</td><td>Fijacion estado</td><td>CVC-</td><td>REGISTRADA</td><td>0</td><td>00002</td></tr>
                </table>
            </form></body></html>
            HTML)
        ->push(<<<'HTML'
            <html><body><form>
                <input type="hidden" name="__VIEWSTATE" value="all-state">
                <input type="hidden" name="__EVENTVALIDATION" value="all-validation">
                <table id="MainContent_GridViewHistoricoActuaciones">
                    <tr><th>Ver</th><th>Fecha registro</th><th>Fecha actuación</th><th>Actuación</th><th>Anotación</th><th>Estado</th><th>Anexos</th><th>Índice</th></tr>
                    <tr><td></td><td>10/01/2024 10:00:00</td><td>10/01/2024</td><td>Radicacion</td><td></td><td>REGISTRADA</td><td>0</td><td>00001</td></tr>
                    <tr><td></td><td>11/01/2024 17:01:42</td><td>12/01/2024</td><td>Fijacion estado</td><td>CVC-</td><td>REGISTRADA</td><td>0</td><td>00002</td></tr>
                </table>
            </form></body></html>
            HTML)
        ->push(<<<'HTML'
            <html><body>
                <table id="MainContent_GVsujetos">
                    <tr><th>Reg</th><th>Tipo de sujeto</th><th>Nombre</th><th>Acceso Web</th></tr>
                    <tr><td>1</td><td>Demandante/accionante</td><td>Manuel Millán</td><td>NO</td></tr>
                </table>
            </body></html>
            HTML);

    $result = app(SamaiPublicPortalService::class)
        ->fetch('76111333300220180006700', '7611133');

    expect($result['actuaciones'])->toHaveCount(2)
        ->and($result['actuaciones'][0]['Orden'])->toBe(1)
        ->and($result['actuaciones'][1]['Orden'])->toBe(2)
        ->and($result['sujetos'])->toHaveCount(1)
        ->and($result['meta'])->toMatchArray([
            'Ponente' => 'JUZGADO 14 ADMINISTRATIVO DE CALI',
            'claseProceso' => 'ACCION DE REPARACION DIRECTA',
            'cityName' => 'VALLE',
            'Vigente' => 'SI',
        ]);

    Http::assertSentCount(4);
    Http::assertSent(fn ($request): bool => str_contains($request->body(), '6336TQ'));
    Http::assertSent(fn ($request): bool => str_contains(
        urldecode($request->body()),
        'ChkVerTodasActuaciones=on'
    ));
    Http::assertSent(fn ($request): bool => str_contains(
        urldecode($request->body()),
        '__EVENTTARGET=ctl00$MainContent$LbtSujetos'
    ));
});

it('expands truncated annotations from the Ver detail panel', function (): void {
    config()->set('samai.public_portal.url', 'https://samai.test');
    config()->set('samai.public_portal.max_attempts', 1);
    config()->set('samai.public_portal.expand_truncated_annotations', true);

    $preview = 'El Señor(a):HÉCTOR JAIME GIRALDO DUQUE con vincula...';
    $full = 'El Señor(a):HÉCTOR JAIME GIRALDO DUQUE con vinculación por sustitución de poder '
        .'para LA PREVISORA S.A. COMPANIA DE SEGUROS. Solicitud No. 3093258.';

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
        ->push(<<<HTML
            <html><body><form>
                <input type="hidden" name="__VIEWSTATE" value="grid-state">
                <input type="hidden" name="__EVENTVALIDATION" value="grid-validation">
                <span id="MainContent_LblTotalRegistros">Total registros: 1</span>
                <span id="MainContent_lblpagini">1</span>
                <span id="MainContent_Lblpagfin">1</span>
                <table id="MainContent_GridViewHistoricoActuaciones">
                    <tr><th>Ver</th><th>Fecha registro</th><th>Fecha actuación</th><th>Actuación</th><th>Anotación</th><th>Estado</th><th>Anexos</th><th>Índice</th></tr>
                    <tr>
                        <td><a href="javascript:__doPostBack('ctl00\$MainContent\$GridViewHistoricoActuaciones\$ctl02\$LinkButton1','')">Ver</a></td>
                        <td>17/07/2026 10:00:00</td>
                        <td>17/07/2026</td>
                        <td>Recepción de Memoriales</td>
                        <td>{$preview}</td>
                        <td>REGISTRADA</td>
                        <td>0</td>
                        <td>00001</td>
                    </tr>
                </table>
            </form></body></html>
            HTML)
        ->push(<<<HTML
            <html><body><form>
                <input type="hidden" name="__VIEWSTATE" value="detail-state">
                <input type="hidden" name="__EVENTVALIDATION" value="detail-validation">
                <span id="MainContent_Txtanotactu">{$full}</span>
            </form></body></html>
            HTML)
        ->push(<<<'HTML'
            <html><body>
                <table id="MainContent_GVsujetos">
                    <tr><th>Reg</th><th>Tipo de sujeto</th><th>Nombre</th><th>Acceso Web</th></tr>
                    <tr><td>1</td><td>Demandante/accionante</td><td>Manuel Millán</td><td>NO</td></tr>
                </table>
            </body></html>
            HTML);

    $result = app(SamaiPublicPortalService::class)
        ->fetch('76001333301320160005700', '7600133');

    expect($result['actuaciones'])->toHaveCount(1)
        ->and($result['actuaciones'][0]['Anotacion'])->toBe($full)
        ->and($result['actuaciones'][0])->not->toHaveKey('_ver_event_target');

    Http::assertSent(fn ($request): bool => str_contains(
        urldecode($request->body()),
        "__EVENTTARGET=ctl00\$MainContent\$GridViewHistoricoActuaciones\$ctl02\$LinkButton1"
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
            'meta' => ['Ponente' => 'Juzgado 14 Administrativo de Cali'],
        ]);

    $service = new SamaiConsultService($portal);

    $actuaciones = $service->obtenerActuaciones('7611133', '76111333300220180006700');
    $sujetos = $service->obtenerSujetosProcesales('7611133', '76111333300220180006700');

    expect($actuaciones->isSuccessful)->toBeTrue()
        ->and($actuaciones->data)->toHaveCount(1)
        ->and($sujetos->isSuccessful)->toBeTrue()
        ->and($sujetos->data)->toHaveCount(1);
});

it('falls back to the public portal when the REST API returns an empty list', function (): void {
    config()->set('samai.api_key', 'fake-key');
    config()->set('samai.api_url', 'https://samai-api.test/api');
    config()->set('samai.modo', '2');
    config()->set('samai.public_portal.enabled', true);
    config()->set('samai.call_delay_min_ms', 0);
    config()->set('samai.call_delay_max_ms', 0);

    Http::fake([
        'https://samai-api.test/api/Procesos/HistorialActuaciones/*' => Http::response([], 200),
        'https://samai-api.test/api/Procesos/SujetosProcesales/*' => Http::response([], 200),
    ]);

    $portal = Mockery::mock(SamaiPublicPortalService::class);
    $portal->shouldReceive('fetch')
        ->once()
        ->with('76111333300220180006700', '7611133')
        ->andReturn([
            'actuaciones' => [['Orden' => 11, 'NombreActuacion' => 'Fijacion estado']],
            'sujetos' => [['NombreRazonSocial' => 'Manuel Millán']],
            'meta' => [],
        ]);

    $service = new SamaiConsultService($portal);

    $actuaciones = $service->obtenerActuaciones('7611133', '76111333300220180006700');
    $sujetos = $service->obtenerSujetosProcesales('7611133', '76111333300220180006700');

    expect($actuaciones->isSuccessful)->toBeTrue()
        ->and($actuaciones->data)->toHaveCount(1)
        ->and($sujetos->isSuccessful)->toBeTrue()
        ->and($sujetos->data)->toHaveCount(1);
});

it('discovers the corporacion via public portal when REST discovery times out', function (): void {
    config()->set('samai.api_key', '');
    config()->set('samai.api_url', 'https://samai-api.test/api');
    config()->set('samai.modo', '2');
    config()->set('samai.public_portal.enabled', true);
    config()->set('samai.discovery_timeout', 1);
    config()->set('samai.call_delay_min_ms', 0);
    config()->set('samai.call_delay_max_ms', 0);

    Http::fake([
        'https://samai-api.test/api/BuscarProcesoTodoSamai/*' => Http::response(['message' => 'Unauthorized'], 401),
        'https://samai-api.test/api/ObtenerDatosProcesoGet/*' => function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Operation timed out after 1001 milliseconds');
        },
    ]);

    $portal = Mockery::mock(SamaiPublicPortalService::class);
    $portal->shouldReceive('fetch')
        ->once()
        ->with('76001333301920210014900', '7600133')
        ->andReturn([
            'actuaciones' => [['Orden' => 1]],
            'sujetos' => [['NombreRazonSocial' => 'Demandante']],
            'meta' => ['claseProceso' => 'NULIDAD'],
        ]);

    $service = new SamaiConsultService($portal);
    $result = $service->buscarProceso('76001333301920210014900');

    expect($result)->toHaveCount(1)
        ->and($result[0]['Corporacion'])->toBe('7600133');
});

it('uses portal metadata when ObtenerDatosProcesoGet returns empty', function (): void {
    config()->set('samai.api_key', '');
    config()->set('samai.api_url', 'https://samai-api.test/api');
    config()->set('samai.modo', '2');
    config()->set('samai.public_portal.enabled', true);
    config()->set('samai.discovery_timeout', 5);
    config()->set('samai.call_delay_min_ms', 0);
    config()->set('samai.call_delay_max_ms', 0);

    Http::fake([
        'https://samai-api.test/api/ObtenerDatosProcesoGet/*' => Http::response(['auditoria' => new stdClass], 200),
    ]);

    $portal = Mockery::mock(SamaiPublicPortalService::class);
    $portal->shouldReceive('fetch')
        ->once()
        ->with('76001333301320160005700', '7600133')
        ->andReturn([
            'actuaciones' => [['Orden' => 1]],
            'sujetos' => [['NombreRazonSocial' => 'Actor']],
            'meta' => [
                'Ponente' => 'JUZGADO 14 ADMINISTRATIVO DE CALI',
                'claseProceso' => 'ACCION DE REPARACION DIRECTA',
            ],
        ]);

    $service = new SamaiConsultService($portal);
    $data = $service->obtenerDatosProceso('7600133', '76001333301320160005700');

    expect($data)->toMatchArray([
        'Ponente' => 'JUZGADO 14 ADMINISTRATIVO DE CALI',
        'claseProceso' => 'ACCION DE REPARACION DIRECTA',
    ]);
});
