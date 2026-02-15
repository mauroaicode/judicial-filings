<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Src\Application\Shared\Services\Alert\OllamaAnnotationAlertDetectionProvider;

beforeEach(function (): void {
    $this->provider = new OllamaAnnotationAlertDetectionProvider;
});

it('returns empty spans for empty annotation', function (): void {
    expect($this->provider->getDetectedAlertSpans(''))->toBe([]);
});

it('uses fallback spans when ollama base_url is not configured', function (): void {
    Config::set('alert-ai.ollama.base_url', '');

    $annotation = 'Se abre período de CONSULTA de pruebas.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->not->toBe([]);
    expect($spans[0])->toHaveKeys(['start', 'end', 'text']);
    expect($spans[0]['text'])->toBe('CONSULTA');
});

it('uses fallback when Ollama API returns error', function (): void {
    Config::set('alert-ai.ollama.base_url', 'http://127.0.0.1:11434');
    Http::fake([
        '127.0.0.1:11434/*' => Http::response(['error' => 'connection refused'], 500),
    ]);

    $annotation = 'Texto con APELACIÓN aquí.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->not->toBe([]);
    expect($spans[0]['text'])->toBe('APELACIÓN');
});

it('parses Ollama chat response and returns spans with correct positions', function (): void {
    Config::set('alert-ai.ollama.base_url', 'http://127.0.0.1:11434');
    Config::set('alert-ai.ollama.model', 'llama3.2:3b');
    Http::fake([
        '127.0.0.1:11434/*' => Http::response([
            'message' => [
                'role' => 'assistant',
                'content' => 'Sí. Fragmentos: CONSULTA, APELACIÓN.',
            ],
            'done' => true,
        ], 200),
    ]);

    $annotation = 'Se abre CONSULTA y luego APELACIÓN.';
    $spans = $this->provider->getDetectedAlertSpans($annotation);

    expect($spans)->toHaveCount(2);
    expect($spans[0]['text'])->toBe('CONSULTA');
    expect($spans[1]['text'])->toBe('APELACIÓN');
});
