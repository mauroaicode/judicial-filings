<?php

declare(strict_types=1);

use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

it('filters subjects by process', function (): void {
    $process = Process::factory()->create();
    $subject = ProcessSubject::factory()->forProcess($process)->create();

    $result = ProcessSubject::query()
        ->whereProcess($process->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($subject->id);
});

it('filters subjects by subject registration id', function (): void {
    $subject = ProcessSubject::factory()->create([
        'subject_registration_id' => 123456,
    ]);

    $result = ProcessSubject::query()
        ->whereSubjectRegistrationId(123456)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($subject->id);
});

it('filters subjects by process and registration id', function (): void {
    $process = Process::factory()->create();
    $subject = ProcessSubject::factory()->forProcess($process)->create([
        'subject_registration_id' => 123456,
    ]);

    $result = ProcessSubject::query()
        ->whereProcessAndRegistrationId($process->id, 123456)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($subject->id);
});

it('filters subjects by subject type', function (): void {
    $subject = ProcessSubject::factory()->create([
        'subject_type' => 'Demandante',
    ]);

    $result = ProcessSubject::query()
        ->whereSubjectType('Demandante')
        ->first();

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($subject->id);
});

it('filters subjects that are cited', function (): void {
    $citedSubject = ProcessSubject::factory()->create([
        'is_cited' => true,
    ]);
    $notCitedSubject = ProcessSubject::factory()->create([
        'is_cited' => false,
    ]);

    $results = ProcessSubject::query()
        ->whereCited()
        ->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($citedSubject->id);
});

it('orders subjects by subject type', function (): void {
    $process = Process::factory()->create();
    $defendant = ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandado',
    ]);
    $plaintiff = ProcessSubject::factory()->forProcess($process)->create([
        'subject_type' => 'Demandante',
    ]);

    $results = ProcessSubject::query()
        ->whereProcess($process->id)
        ->orderedBySubjectType()
        ->get();

    expect($results->first()->id)->toBe($defendant->id);
    expect($results->last()->id)->toBe($plaintiff->id);
});
