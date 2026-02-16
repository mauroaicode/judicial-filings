<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Src\Application\AppUser\Process\Services\ProcessSubjectService;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessSubject;

beforeEach(function (): void {
    $this->judicialBranchConsultService = $this->app->make(JudicialBranchConsultService::class);
    $this->processSubjectService = new ProcessSubjectService($this->judicialBranchConsultService);
    $this->processId = random_int(1000000000, 9999999999);
    $this->process = Process::factory()->create([
        'process_id' => $this->processId,
    ]);
    $this->subjectRegistrationId1 = random_int(10000000, 99999999);
    $this->subjectRegistrationId2 = random_int(10000000, 99999999);
    $this->subjectRegistrationId3 = random_int(10000000, 99999999);
});

it('fetches and saves subjects from judicial branch', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}*" => Http::response([
            'sujetos' => [
                [
                    'idRegSujeto' => $this->subjectRegistrationId1,
                    'tipoSujeto' => 'Demandante',
                    'esEmplazado' => false,
                    'identificacion' => null,
                    'nombreRazonSocial' => 'JUAN GABRIEL VILLALOBOS GIRALDO',
                    'cant' => 2,
                ],
                [
                    'idRegSujeto' => $this->subjectRegistrationId2,
                    'tipoSujeto' => 'Demandado',
                    'esEmplazado' => false,
                    'identificacion' => null,
                    'nombreRazonSocial' => 'UNIDAD ADMINISTRATIVA ESPECIAL DE GESTION PENSIONAL Y CONTRIBUCIONES PARAFISCALES',
                    'cant' => 2,
                ],
            ],
            'paginacion' => [
                'cantidadRegistros' => 2,
                'registrosPagina' => 40,
                'cantidadPaginas' => 1,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subjects = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($subjects)->toHaveCount(2);

    $registrationIds = $subjects->pluck('subject_registration_id')->toArray();
    expect($registrationIds)->toContain($this->subjectRegistrationId1);
    expect($registrationIds)->toContain($this->subjectRegistrationId2);

    $plaintiff = $subjects->firstWhere('subject_registration_id', $this->subjectRegistrationId1);
    expect($plaintiff)->not->toBeNull();
    expect($plaintiff->subject_type)->toBe('Demandante');
    expect($plaintiff->name_or_business_name)->toBe('JUAN GABRIEL VILLALOBOS GIRALDO');
    expect($plaintiff->is_cited)->toBeFalse();

    $defendant = $subjects->firstWhere('subject_registration_id', $this->subjectRegistrationId2);
    expect($defendant)->not->toBeNull();
    expect($defendant->subject_type)->toBe('Demandado');
    expect($defendant->name_or_business_name)->toBe('UNIDAD ADMINISTRATIVA ESPECIAL DE GESTION PENSIONAL Y CONTRIBUCIONES PARAFISCALES');
    expect($defendant->is_cited)->toBeFalse();
});

it('does not create duplicate subjects', function (): void {
    $existingSubject = ProcessSubject::factory()->forProcess($this->process)->create([
        'subject_registration_id' => $this->subjectRegistrationId1,
    ]);

    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}*" => Http::response([
            'sujetos' => [
                [
                    'idRegSujeto' => $this->subjectRegistrationId1,
                    'tipoSujeto' => 'Demandante',
                    'esEmplazado' => false,
                    'identificacion' => null,
                    'nombreRazonSocial' => 'JUAN GABRIEL VILLALOBOS GIRALDO',
                    'cant' => 2,
                ],
            ],
            'paginacion' => [
                'cantidadRegistros' => 1,
                'registrosPagina' => 40,
                'cantidadPaginas' => 1,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subjects = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($subjects)->toHaveCount(1);
    expect($subjects->first()->id)->toBe($existingSubject->id);
});

it('handles empty subjects response gracefully', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}*" => Http::response([
            'sujetos' => [],
            'paginacion' => [
                'cantidadRegistros' => 0,
                'registrosPagina' => 40,
                'cantidadPaginas' => 1,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subjects = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($subjects)->toHaveCount(0);
});

it('handles failed API response gracefully', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}*" => Http::response([], 500),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subjects = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($subjects)->toHaveCount(0);
});

it('skips subjects without registration id', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}*" => Http::response([
            'sujetos' => [
                [
                    'tipoSujeto' => 'Demandante',
                    'esEmplazado' => false,
                    'identificacion' => null,
                    'nombreRazonSocial' => 'JUAN GABRIEL VILLALOBOS GIRALDO',
                ],
            ],
            'paginacion' => [
                'cantidadRegistros' => 1,
                'registrosPagina' => 40,
                'cantidadPaginas' => 1,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subjects = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($subjects)->toHaveCount(0);
});

it('handles paginated subjects correctly', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}?pagina=1*" => Http::response([
            'sujetos' => [
                [
                    'idRegSujeto' => $this->subjectRegistrationId1,
                    'tipoSujeto' => 'Demandante',
                    'esEmplazado' => false,
                    'identificacion' => null,
                    'nombreRazonSocial' => 'JUAN GABRIEL VILLALOBOS GIRALDO',
                    'cant' => 2,
                ],
            ],
            'paginacion' => [
                'cantidadRegistros' => 2,
                'registrosPagina' => 40,
                'cantidadPaginas' => 2,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}?pagina=2*" => Http::response([
            'sujetos' => [
                [
                    'idRegSujeto' => $this->subjectRegistrationId2,
                    'tipoSujeto' => 'Demandado',
                    'esEmplazado' => false,
                    'identificacion' => null,
                    'nombreRazonSocial' => 'UNIDAD ADMINISTRATIVA ESPECIAL DE GESTION PENSIONAL Y CONTRIBUCIONES PARAFISCALES',
                    'cant' => 2,
                ],
            ],
            'paginacion' => [
                'cantidadRegistros' => 2,
                'registrosPagina' => 40,
                'cantidadPaginas' => 2,
                'pagina' => 2,
                'paginas' => null,
            ],
        ], 200),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subjects = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($subjects)->toHaveCount(2);
    expect($subjects->pluck('subject_registration_id')->toArray())->toContain($this->subjectRegistrationId1);
    expect($subjects->pluck('subject_registration_id')->toArray())->toContain($this->subjectRegistrationId2);
});

it('saves subject with identification and cited status', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$this->processId}*" => Http::response([
            'sujetos' => [
                [
                    'idRegSujeto' => $this->subjectRegistrationId1,
                    'tipoSujeto' => 'Demandante',
                    'esEmplazado' => true,
                    'identificacion' => '1234567890',
                    'nombreRazonSocial' => 'JUAN GABRIEL VILLALOBOS GIRALDO',
                    'cant' => 2,
                ],
            ],
            'paginacion' => [
                'cantidadRegistros' => 1,
                'registrosPagina' => 40,
                'cantidadPaginas' => 1,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
    ]);

    $this->processSubjectService->handle($this->process, $this->processId);

    $subject = ProcessSubject::query()
        ->whereProcess($this->process->id)
        ->first();

    expect($subject)->not->toBeNull();
    expect($subject->identification)->toBe('1234567890');
    expect($subject->is_cited)->toBeTrue();
});
