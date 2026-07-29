<?php

declare(strict_types=1);

use Src\Domain\Process\Services\FijacionEstadoActionSplitter;

it('splits combined fijacion estado + auto titles', function (): void {
    $splitter = new FijacionEstadoActionSplitter;

    expect($splitter->split('Fijacion Estado Auto Admite Demanda'))
        ->toBe(['Fijación Estado', 'Auto Admite Demanda']);
});

it('splits notificacion estado combined titles', function (): void {
    $splitter = new FijacionEstadoActionSplitter;

    expect($splitter->split('Notificacion Estado Auto Ordena Acumular a Este Proceso'))
        ->toBe(['Notificación Estado', 'Auto Ordena Acumular a Este Proceso']);
});

it('leaves plain actuaciones untouched', function (): void {
    $splitter = new FijacionEstadoActionSplitter;

    expect($splitter->split('Auto Admite Demanda'))
        ->toBe(['Auto Admite Demanda'])
        ->and($splitter->split('Fijacion Estado'))
        ->toBe(['Fijacion Estado']);
});

it('does not split fijacion estado without a linked decision', function (): void {
    $splitter = new FijacionEstadoActionSplitter;

    expect($splitter->split('Fijacion Estado del proceso'))
        ->toBe(['Fijacion Estado del proceso']);
});
