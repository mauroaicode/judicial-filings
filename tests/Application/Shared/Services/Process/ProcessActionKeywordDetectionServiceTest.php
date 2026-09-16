<?php

declare(strict_types=1);

use Src\Application\Shared\Services\Process\ProcessActionKeywordDetectionService;
use Src\Domain\Keyword\Models\Keyword;
use Src\Domain\Process\Models\ProcessAction;

it('detects a multi-word keyword inside the action text', function (): void {
    $action = new ProcessAction([
        'annotation' => 'Notificacion por Estado',
        'action' => 'Auto Convoca Audiencia Inicial',
    ]);
    $keyword = new Keyword([
        'name' => 'AUDIENCIA',
        'keyword' => 'Audiencia Inicial',
    ]);

    $results = app(ProcessActionKeywordDetectionService::class)
        ->handle($action, collect([$keyword]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['matches'][0]['text'])->toBe('Audiencia Inicial')
        ->and($results->first()['matches'][0]['source'])->toBe('action');
});

it('still detects a single-word keyword ignoring accents', function (): void {
    $action = new ProcessAction([
        'annotation' => '',
        'action' => 'Notificacion por Estado',
    ]);
    $keyword = new Keyword([
        'name' => 'NOTIFICACIÓN',
        'keyword' => 'notificación',
    ]);

    $results = app(ProcessActionKeywordDetectionService::class)
        ->handle($action, collect([$keyword]));

    expect($results)->toHaveCount(1)
        ->and($results->first()['matches'][0]['text'])->toBe('Notificacion');
});
