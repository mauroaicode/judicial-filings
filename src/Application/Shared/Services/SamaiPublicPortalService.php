<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Exceptions\SamaiPublicPortalException;
use Throwable;

/**
 * Consulta actuaciones, sujetos y metadatos desde la vista pública HTML de SAMAI.
 *
 * Este cliente es el fallback para instalaciones sin ApiKey. Mantiene una
 * sesión ASP.NET, resuelve el captcha textual expuesto por la propia página y
 * conserva ViewState/EventValidation entre los postbacks.
 *
 * El historial de actuaciones está paginado. Por defecto SAMAI muestra la
 * última página; se activa "Ver todas las actuaciones" para obtener el
 * historial completo en una sola grilla.
 */
class SamaiPublicPortalService
{
    /**
     * @return array{
     *     actuaciones: list<array<string, mixed>>,
     *     sujetos: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    public function fetch(string $processNumber, string $corporacion): array
    {
        $maxAttempts = max(1, (int) config('samai.public_portal.max_attempts', 3));
        $lastError = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->fetchAttempt($processNumber, $corporacion);
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        throw new SamaiPublicPortalException(
            'No fue posible consultar el portal público de SAMAI después de '
            .$maxAttempts.' intento(s).',
            previous: $lastError,
        );
    }

    /**
     * @return array{
     *     actuaciones: list<array<string, mixed>>,
     *     sujetos: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }
     */
    private function fetchAttempt(string $processNumber, string $corporacion): array
    {
        $url = $this->processUrl($processNumber, $corporacion);
        $client = $this->client(new CookieJar);

        $initialResponse = $client->get($url)->throw();
        $initialDocument = $this->parseDocument($initialResponse->body());
        $captcha = $this->extractCaptcha($initialDocument);

        $captchaPayload = $this->hiddenFields($initialDocument);
        $captchaPayload['ctl00$MainContent$TxtCaptcha2'] = $captcha;
        $captchaPayload['ctl00$MainContent$CmdNoRobot'] = 'Continuar';

        $processResponse = $client->asForm()->post($url, $captchaPayload)->throw();
        $processDocument = $this->parseDocument($processResponse->body());

        if ($this->hasElement($processDocument, 'MainContent_TxtCaptcha2')) {
            throw new SamaiPublicPortalException('SAMAI rechazó el captcha del portal público.');
        }

        if (! $this->hasElement($processDocument, 'MainContent_GridViewHistoricoActuaciones')) {
            throw new SamaiPublicPortalException('SAMAI no mostró el historial público del proceso.');
        }

        $meta = $this->parseMeta($processDocument);
        $processDocument = $this->expandActuacionesHistorial($client, $url, $processDocument);

        $actuaciones = $this->parseActuaciones($processDocument);
        if ($actuaciones === []) {
            throw new SamaiPublicPortalException('SAMAI devolvió el historial público sin actuaciones.');
        }

        $actuaciones = $this->expandTruncatedAnnotations($client, $url, $processDocument, $actuaciones);

        $subjectPayload = $this->hiddenFields($processDocument);
        $subjectPayload['__EVENTTARGET'] = 'ctl00$MainContent$LbtSujetos';
        $subjectPayload['__EVENTARGUMENT'] = '';

        $subjectsResponse = $client->asForm()->post($url, $subjectPayload)->throw();
        $subjectsDocument = $this->parseDocument($subjectsResponse->body());

        if (! $this->hasElement($subjectsDocument, 'MainContent_GVsujetos')) {
            throw new SamaiPublicPortalException('SAMAI no mostró los sujetos públicos del proceso.');
        }

        $sujetos = $this->parseSujetos($subjectsDocument);
        if ($sujetos === []) {
            throw new SamaiPublicPortalException('SAMAI devolvió la tabla de sujetos sin filas.');
        }

        return [
            'actuaciones' => $actuaciones,
            'sujetos' => $sujetos,
            'meta' => $meta,
        ];
    }

    /**
     * Activa "Ver todas las actuaciones" cuando el historial está paginado.
     * Sin esto SAMAI suele devolver solo la última página.
     */
    private function expandActuacionesHistorial(
        PendingRequest $client,
        string $url,
        DOMDocument $processDocument,
    ): DOMDocument {
        if (! $this->hasElement($processDocument, 'MainContent_ChkVerTodasActuaciones')) {
            return $processDocument;
        }

        $totalPages = (int) $this->elementText($processDocument, 'MainContent_Lblpagfin');
        $totalRegistros = $this->parseTotalRegistros($processDocument);
        $currentCount = count($this->parseActuaciones($processDocument));

        $needsExpansion = $totalPages > 1
            || ($totalRegistros > 0 && $currentCount > 0 && $currentCount < $totalRegistros);

        if (! $needsExpansion) {
            return $processDocument;
        }

        $payload = $this->hiddenFields($processDocument);
        $payload['ctl00$MainContent$ChkVerTodasActuaciones'] = 'on';
        $payload['__EVENTTARGET'] = 'ctl00$MainContent$ChkVerTodasActuaciones';
        $payload['__EVENTARGUMENT'] = '';

        $response = $client->asForm()->post($url, $payload)->throw();
        $expandedDocument = $this->parseDocument($response->body());

        if (! $this->hasElement($expandedDocument, 'MainContent_GridViewHistoricoActuaciones')) {
            throw new SamaiPublicPortalException('SAMAI no expandió el historial completo de actuaciones.');
        }

        return $expandedDocument;
    }

    private function client(CookieJar $cookieJar): PendingRequest
    {
        return Http::timeout((int) config('samai.public_portal.timeout', 60))
            ->connectTimeout((int) config('samai.public_portal.connect_timeout', 15))
            ->withOptions(['cookies' => $cookieJar])
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-CO,es;q=0.9',
                'User-Agent' => (string) config(
                    'samai.public_portal.user_agent',
                    'Mozilla/5.0 (compatible; NotiJudicial/1.0)'
                ),
            ]);
    }

    private function processUrl(string $processNumber, string $corporacion): string
    {
        $baseUrl = rtrim(
            (string) config('samai.public_portal.url', 'https://samai.consejodeestado.gov.co'),
            '/'
        );

        return $baseUrl.'/Vistas/Casos/list_procesos.aspx?guid='
            .rawurlencode($processNumber.$corporacion);
    }

    private function parseDocument(string $html): DOMDocument
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML(
                '<?xml encoding="utf-8" ?>'.$html,
                LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new SamaiPublicPortalException('SAMAI devolvió HTML inválido.');
        }

        return $document;
    }

    private function extractCaptcha(DOMDocument $document): string
    {
        $xpath = new DOMXPath($document);
        $captcha = '';

        foreach (['MainContent_Lbldato1', 'MainContent_Lbldato2', 'MainContent_Lbldato3'] as $id) {
            $nodes = $xpath->query("//*[@id='{$id}']");
            $node = $nodes !== false ? $nodes->item(0) : null;
            $captcha .= $node instanceof \DOMNode ? $node->textContent : '';
        }

        $captcha = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $captcha));

        if (strlen($captcha) < 5) {
            throw new SamaiPublicPortalException('No se pudo leer el captcha textual de SAMAI.');
        }

        return $captcha;
    }

    /**
     * @return array<string, string>
     */
    private function hiddenFields(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $payload = [];

        foreach ($xpath->query('//input[@type="hidden"]') ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $name = $node->getAttribute('name');
            if ($name !== '') {
                $payload[$name] = $node->getAttribute('value');
            }
        }

        return $payload;
    }

    private function hasElement(DOMDocument $document, string $id): bool
    {
        $nodes = (new DOMXPath($document))->query("//*[@id='{$id}']");

        return $nodes !== false && $nodes->length === 1;
    }

    private function elementText(DOMDocument $document, string $id): string
    {
        $nodes = (new DOMXPath($document))->query("//*[@id='{$id}']");
        $node = $nodes !== false ? $nodes->item(0) : null;

        if (! $node instanceof \DOMNode) {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    private function parseTotalRegistros(DOMDocument $document): int
    {
        $text = $this->elementText($document, 'MainContent_LblTotalRegistros');
        if (preg_match('/(\d+)/', $text, $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    /**
     * Metadatos públicos alineados a los nombres de ObtenerDatosProcesoGet.
     *
     * @return array<string, mixed>
     */
    private function parseMeta(DOMDocument $document): array
    {
        $ponente = $this->elementText($document, 'MainContent_LblPonente');
        $clase = $this->elementText($document, 'MainContent_lblclase1');
        $nombreCorporacion = rtrim($this->elementText($document, 'MainContent_LblNombreCorporacion'), '- ');
        $origen = $this->elementText($document, 'MainContent_LblorigenNombre');
        $vigente = $this->elementText($document, 'MainContent_LblEVigente');
        $fechaRad = $this->elementText($document, 'MainContent_Lblfecharad');

        $cityName = '';
        if (preg_match('/\(([^)]+)\)\s*$/', $origen, $matches) === 1) {
            $cityName = trim($matches[1]);
        }

        return array_filter([
            'Ponente' => $ponente,
            'claseProceso' => $clase,
            'NombreSalaDecision' => $nombreCorporacion !== '' ? $nombreCorporacion : null,
            'cityName' => $cityName !== '' ? $cityName : null,
            'Vigente' => $vigente !== '' ? $vigente : null,
            'FECHAPROC' => $fechaRad !== '' ? $fechaRad : null,
            'EntidadRadicadora' => $origen !== '' ? $origen : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseActuaciones(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $rows = $xpath->query('//*[@id="MainContent_GridViewHistoricoActuaciones"]//tr');
        $actuaciones = [];

        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false) {
                continue;
            }

            if ($cells->length < 8) {
                continue;
            }

            $orden = (int) ltrim($this->cellText($cells->item(7)), '0');
            if ($orden === 0) {
                continue;
            }

            $actuaciones[] = [
                'Orden' => $orden,
                'Actuacion' => $this->cellText($cells->item(2)),
                'NombreActuacion' => $this->cellText($cells->item(3)),
                'Anotacion' => $this->cellText($cells->item(4)),
                'Registro' => $this->cellText($cells->item(1)),
                'Estado' => $this->cellText($cells->item(5)),
                'Anexos' => (int) $this->cellText($cells->item(6)),
                '_ver_event_target' => $this->extractVerEventTarget($xpath, $cells->item(0)),
            ];
        }

        return $actuaciones;
    }

    /**
     * El grid público trunca anotaciones (~55 chars + "..."). El texto completo
     * vive en el detalle "Ver" (MainContent_Txtanotactu). Reutiliza el ViewState
     * de la grilla expandida para no perder la sesión entre detalles.
     *
     * @param  list<array<string, mixed>>  $actuaciones
     * @return list<array<string, mixed>>
     */
    private function expandTruncatedAnnotations(
        PendingRequest $client,
        string $url,
        DOMDocument $gridDocument,
        array $actuaciones,
    ): array {
        if (! (bool) config('samai.public_portal.expand_truncated_annotations', true)) {
            return $this->stripInternalActuacionKeys($actuaciones);
        }

        $maxExpand = max(0, (int) config('samai.public_portal.max_expanded_annotations', 150));
        if ($maxExpand === 0) {
            return $this->stripInternalActuacionKeys($actuaciones);
        }

        $gridPayload = $this->hiddenFields($gridDocument);
        $expanded = 0;

        foreach ($actuaciones as $index => $actuacion) {
            $anotacion = trim((string) ($actuacion['Anotacion'] ?? ''));
            $target = (string) ($actuacion['_ver_event_target'] ?? '');

            if ($target === '' || ! str_ends_with($anotacion, '...')) {
                continue;
            }

            if ($expanded >= $maxExpand) {
                break;
            }

            $payload = $gridPayload;
            $payload['__EVENTTARGET'] = $target;
            $payload['__EVENTARGUMENT'] = '';

            try {
                $detailDocument = $this->parseDocument(
                    $client->asForm()->post($url, $payload)->throw()->body()
                );
                $full = $this->elementText($detailDocument, 'MainContent_Txtanotactu');
                if ($full !== '' && strlen($full) > strlen($anotacion)) {
                    $actuaciones[$index]['Anotacion'] = $full;
                    $expanded++;
                }
            } catch (Throwable) {
                // Conserva el preview truncado si el detalle falla.
                continue;
            }
        }

        return $this->stripInternalActuacionKeys($actuaciones);
    }

    /**
     * @param  list<array<string, mixed>>  $actuaciones
     * @return list<array<string, mixed>>
     */
    private function stripInternalActuacionKeys(array $actuaciones): array
    {
        foreach ($actuaciones as $index => $actuacion) {
            unset($actuaciones[$index]['_ver_event_target']);
        }

        return $actuaciones;
    }

    private function extractVerEventTarget(DOMXPath $xpath, ?\DOMNode $cell): string
    {
        if (! $cell instanceof \DOMNode) {
            return '';
        }

        $links = $xpath->query('.//a[contains(@href,"__doPostBack")]', $cell);
        $link = $links !== false ? $links->item(0) : null;
        if (! $link instanceof DOMElement) {
            return '';
        }

        $href = html_entity_decode($link->getAttribute('href'));
        if (preg_match("/__doPostBack\\('([^']+)'/", $href, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSujetos(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $rows = $xpath->query('//*[@id="MainContent_GVsujetos"]//tr');
        $sujetos = [];

        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            $cells = $xpath->query('./td', $row);
            if ($cells === false) {
                continue;
            }

            if ($cells->length < 4) {
                continue;
            }

            $name = $this->cellText($cells->item(2));
            if ($name === '') {
                continue;
            }

            $sujetos[] = [
                'TipoSujeto' => $this->cellText($cells->item(1)),
                'NombreRazonSocial' => $name,
                'Identificacion' => null,
                'EsEmplazado' => false,
            ];
        }

        return $sujetos;
    }

    private function cellText(?\DOMNode $node): string
    {
        if (! $node instanceof \DOMNode) {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }
}
