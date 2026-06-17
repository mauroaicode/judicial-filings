<?php

declare(strict_types=1);

use Src\Application\Shared\Helpers\ProcessPhantomInstanceHelper;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

it('detects phantom instance when sibling has subjects and metadata matches', function (): void {
    $rich = Process::factory()->create([
        'process_number' => '76001400303420230073500',
        'court' => 'JUZGADO 034 CIVIL MUNICIPAL DE CALI',
        'department' => 'VALLE DEL CAUCA',
        'process_date' => '2023-08-30',
        'litigants' => null,
    ]);

    $phantom = Process::factory()->create([
        'process_number' => '76001400303420230073500',
        'court' => 'JUZGADO 034 CIVIL MUNICIPAL DE CALI',
        'department' => 'VALLE DEL CAUCA',
        'process_date' => '2023-08-30',
        'litigants' => null,
    ]);

    $subject = ProcessSubject::factory()->create();
    $rich->subjects()->attach($subject->id);

    $siblings = Process::query()->where('process_number', '76001400303420230073500')->get();

    expect(ProcessPhantomInstanceHelper::isLikelyPhantomDuplicate($rich, $siblings))->toBeFalse();
    expect(ProcessPhantomInstanceHelper::isLikelyPhantomDuplicate($phantom, $siblings))->toBeTrue();
});

it('does not treat real multi-instance radicados as phantom when all instances are rich', function (): void {
    $first = Process::factory()->create([
        'process_number' => '76001400303420230073500',
        'court' => 'JUZGADO 001',
        'department' => 'BOGOTA',
        'process_date' => '2023-08-30',
        'litigants' => 'Demandante: A',
    ]);

    $second = Process::factory()->create([
        'process_number' => '76001400303420230073500',
        'court' => 'JUZGADO 002',
        'department' => 'BOGOTA',
        'process_date' => '2024-01-10',
        'litigants' => 'Demandante: B',
    ]);

    $siblings = Process::query()->where('process_number', '76001400303420230073500')->get();

    expect(ProcessPhantomInstanceHelper::isLikelyPhantomDuplicate($first, $siblings))->toBeFalse();
    expect(ProcessPhantomInstanceHelper::isLikelyPhantomDuplicate($second, $siblings))->toBeFalse();
});
