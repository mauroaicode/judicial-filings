<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Services\Alert\OpenAIAnnotationAlertDetectionProvider;

beforeEach(function (): void {
    $this->provider = new OpenAIAnnotationAlertDetectionProvider;
});

it('returns empty spans for empty annotation', function (): void {
    expect($this->provider->getDetectedAlertSpans(''))->toBe([]);
});

it('returns false for containsAlertKeywords when annotation is empty', function (): void {
    expect($this->provider->containsAlertKeywords(''))->toBeFalse();
});

it('uses fallback spans when API key is not configured', function (): void {
    Config::set('services.openai.api_key', '');

    $annotation = 'Se abre período de CONSULTA de pruebas.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->not->toBe([]);
    expect($spans[0])->toHaveKeys(['start', 'end', 'text']);
    expect($spans[0]['text'])->toBe('CONSULTA');
});

it('uses fallback when OpenAI API returns error', function (): void {
    Config::set('services.openai.api_key', 'sk-test');
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => 'Server error'], 500),
    ]);

    $annotation = 'Texto con APELACIÓN aquí.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->not->toBe([]);
    expect($spans[0]['text'])->toBe('APELACIÓN');
});

it('parses OpenAI response and returns spans with correct positions', function (): void {
    Config::set('services.openai.api_key', 'sk-test');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Sí. Fragmentos: CONSULTA, APELACIÓN.',
                    ],
                ],
            ],
        ], 200),
    ]);

    $annotation = 'Se abre CONSULTA y luego APELACIÓN.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->toHaveCount(2);
    expect($spans[0])->toEqual([
        'start' => 8,
        'end' => 16,
        'text' => 'CONSULTA',
    ]);
    expect($spans[1])->toEqual([
        'start' => 25,
        'end' => 34,
        'text' => 'APELACIÓN',
    ]);
});

it('containsAlertKeywords returns true when spans are detected', function (): void {
    Config::set('services.openai.api_key', 'sk-test');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'Sí. Fragmentos: CONSULTA.',
                    ],
                ],
            ],
        ], 200),
    ]);

    expect($this->provider->containsAlertKeywords('Texto con CONSULTA.'))->toBeTrue();
});

it('returns empty when OpenAI responds No', function (): void {
    Config::set('services.openai.api_key', 'sk-test');
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => 'No.',
                    ],
                ],
            ],
        ], 200),
    ]);

    $spans = $this->provider->getDetectedAlertSpans('Solo texto sin palabras clave.');

    expect($spans)->toBe([]);
});

it('fallback finds multiple occurrences of same keyword', function (): void {
    Config::set('services.openai.api_key', '');

    $annotation = 'CONSULTA primera y CONSULTA segunda.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->toHaveCount(2);
    expect($spans[0]['text'])->toBe('CONSULTA');
    expect($spans[1]['text'])->toBe('CONSULTA');
});

it('fallback uses config alert-keywords', function (): void {
    Config::set('services.openai.api_key', '');
    Config::set('alert-keywords.keywords', ['CONSULTA', 'APELACIÓN']);

    $annotation = 'Hay APELACIÓN y CONSULTA.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->toHaveCount(2);
    $texts = array_column($spans, 'text');
    expect($texts)->toContain('APELACIÓN', 'CONSULTA');
});
