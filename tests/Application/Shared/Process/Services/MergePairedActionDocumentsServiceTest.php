<?php

declare(strict_types=1);

use Src\Application\Shared\Process\Services\MergePairedActionDocumentsService;

it('merges documents from the paired auto onto the visible fijacion row', function (): void {
    $service = new MergePairedActionDocumentsService;

    $fijacionId = (string) \Illuminate\Support\Str::uuid();
    $autoId = (string) \Illuminate\Support\Str::uuid();

    $pageItems = collect([
        [
            'id' => $fijacionId,
            'action' => 'Fijacion estado',
            'notified_action_id' => $autoId,
            'documents' => [],
        ],
        [
            'id' => $autoId,
            'action' => 'Auto requiere',
            'fijacion_action_id' => $fijacionId,
            'documents' => [
                ['id' => 46, 'original_filename' => '2026-00069-19OCT2026.pdf'],
            ],
        ],
    ]);

    $merged = $service->handle($pageItems);

    $fijacion = $merged->firstWhere('id', $fijacionId);
    $auto = $merged->firstWhere('id', $autoId);

    expect($fijacion['documents'])->toHaveCount(1)
        ->and($fijacion['documents'][0]['id'])->toBe(46)
        ->and($auto['documents'])->toHaveCount(1)
        ->and($auto['documents'][0]['id'])->toBe(46);
});

it('merges documents from context partner when pair spans pages', function (): void {
    $service = new MergePairedActionDocumentsService;

    $fijacionId = (string) \Illuminate\Support\Str::uuid();
    $autoId = (string) \Illuminate\Support\Str::uuid();

    $pageItems = collect([
        [
            'id' => $autoId,
            'action' => 'Auto pone en conocimiento',
            'fijacion_action_id' => $fijacionId,
            'documents' => [],
        ],
    ]);

    $contextItems = collect([
        [
            'id' => $fijacionId,
            'action' => 'Fijacion estado',
            'notified_action_id' => $autoId,
            'documents' => [
                ['id' => 99, 'original_filename' => 'edicto.pdf'],
            ],
        ],
    ]);

    $merged = $service->handle($pageItems, $contextItems);
    $auto = $merged->firstWhere('id', $autoId);

    expect($auto['documents'])->toHaveCount(1)
        ->and($auto['documents'][0]['id'])->toBe(99);
});
