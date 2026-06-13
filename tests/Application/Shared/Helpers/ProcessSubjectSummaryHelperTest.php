<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Src\Application\Shared\Helpers\ProcessSubjectSummaryHelper;
use Src\Domain\Process\Models\ProcessSubject;

it('groups subjects into plaintiffs defendants and others', function (): void {
    $subjects = new Collection([
        makeSubjectSummary('Demandante', 'Ernesto Bedoya Cruz'),
        makeSubjectSummary('Demandado', 'Seguros del Estado Sa'),
        makeSubjectSummary('Demandado', 'Mauricio Gutierrez'),
        makeSubjectSummary('Apoderado', 'Andres Felipe Romero Manchola'),
        makeSubjectSummary('No Recurrente', 'Ana Maria Marcayata'),
    ]);

    $summary = ProcessSubjectSummaryHelper::summarize($subjects);

    expect($summary['plaintiffs_count'])->toBe(1);
    expect($summary['defendants_count'])->toBe(2);
    expect($summary['others_count'])->toBe(2);
    expect($summary['subjects_count'])->toBe(5);
    expect($summary['plaintiffs'])->toBe(['Ernesto Bedoya Cruz']);
    expect($summary['defendants'])->toContain('Mauricio Gutierrez', 'Seguros del Estado Sa');
    expect($summary['others'])->toContain('Ana Maria Marcayata', 'Andres Felipe Romero Manchola');
    expect($summary['defendant'])->toContain('(+1)');
    expect($summary['other_subject'])->toContain('(+1)');
});

it('classifies demandado principal as defendant', function (): void {
    $subjects = new Collection([
        makeSubjectSummary('Demandado Principal', 'Metro Cali Sa Acuerdo'),
    ]);

    $summary = ProcessSubjectSummaryHelper::summarize($subjects);

    expect($summary['defendants_count'])->toBe(1);
    expect($summary['others_count'])->toBe(0);
});

it('deduplicates the same person linked twice through different registration ids', function (): void {
    $subjects = new Collection([
        ProcessSubject::factory()->make([
            'subject_type' => 'Demandante',
            'name_or_business_name' => 'JULIANA MONDRAGON MANCHOLA',
            'subject_registration_id' => 19617709,
            'identification' => null,
        ]),
        ProcessSubject::factory()->make([
            'subject_type' => 'Demandante',
            'name_or_business_name' => 'JULIANA MONDRAGON MANCHOLA',
            'subject_registration_id' => 24955424,
            'identification' => null,
        ]),
    ]);

    $summary = ProcessSubjectSummaryHelper::summarize($subjects);

    expect($summary['plaintiffs_count'])->toBe(1);
    expect($summary['subjects_count'])->toBe(1);
    expect($summary['plaintiffs'])->toBe(['Juliana Mondragon Manchola']);
});

function makeSubjectSummary(string $type, string $name): ProcessSubject
{
    return ProcessSubject::factory()->make([
        'subject_type' => $type,
        'name_or_business_name' => $name,
    ]);
}
