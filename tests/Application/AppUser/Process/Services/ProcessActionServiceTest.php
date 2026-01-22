<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Src\Application\AppUser\Process\Services\ProcessActionService;
use Src\Application\Shared\Services\JudicialBranchConsultService;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

beforeEach(function (): void {
    $this->judicialBranchConsultService = $this->app->make(JudicialBranchConsultService::class);
    $this->processActionService = new ProcessActionService($this->judicialBranchConsultService);
    $this->processId = random_int(1000000000, 9999999999);
    $this->process = Process::factory()->create([
        'process_id' => $this->processId,
    ]);
});

it('fetches and saves actions from judicial branch', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$this->processId}*" => Http::response([
            'actuaciones' => [
                [
                    'idRegActuacion' => 123456,
                    'fechaActuacion' => '2025-01-01',
                    'actuacion' => 'Test action',
                    'anotacion' => 'Test annotation',
                    'fechaInicial' => null,
                    'fechaFinal' => null,
                    'fechaRegistro' => '2025-01-01',
                ],
                [
                    'idRegActuacion' => 123457,
                    'fechaActuacion' => '2025-01-02',
                    'actuacion' => 'Test action 2',
                    'anotacion' => 'Test annotation 2',
                    'fechaInicial' => '2025-01-01',
                    'fechaFinal' => '2025-01-02',
                    'fechaRegistro' => '2025-01-02',
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $this->processActionService->handle($this->process, $this->processId);

    $actions = ProcessAction::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($actions)->toHaveCount(2);

    $registrationIds = $actions->pluck('action_registration_id')->toArray();
    expect($registrationIds)->toContain(123456);
    expect($registrationIds)->toContain(123457);

    $action1 = $actions->firstWhere('action_registration_id', 123456);
    expect($action1)->not->toBeNull();
    expect($action1->action)->toBe('Test action');

    $action2 = $actions->firstWhere('action_registration_id', 123457);
    expect($action2)->not->toBeNull();
    expect($action2->action)->toBe('Test action 2');
});

it('does not create duplicate actions', function (): void {
    $existingAction = ProcessAction::factory()->create([
        'process_id' => $this->process->id,
        'action_registration_id' => 123456,
    ]);

    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$this->processId}*" => Http::response([
            'actuaciones' => [
                [
                    'idRegActuacion' => 123456,
                    'fechaActuacion' => '2025-01-01',
                    'actuacion' => 'Test action',
                    'anotacion' => 'Test annotation',
                    'fechaInicial' => null,
                    'fechaFinal' => null,
                    'fechaRegistro' => '2025-01-01',
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $this->processActionService->handle($this->process, $this->processId);

    $actions = ProcessAction::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($actions)->toHaveCount(1);
    expect($actions->first()->id)->toBe($existingAction->id);
});

it('handles empty actions response gracefully', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$this->processId}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $this->processActionService->handle($this->process, $this->processId);

    $actions = ProcessAction::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($actions)->toHaveCount(0);
});

it('handles failed API response gracefully', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$this->processId}*" => Http::response([], 500),
    ]);

    $this->processActionService->handle($this->process, $this->processId);

    $actions = ProcessAction::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($actions)->toHaveCount(0);
});

it('skips actions without registration id', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$this->processId}*" => Http::response([
            'actuaciones' => [
                [
                    'fechaActuacion' => '2025-01-01',
                    'actuacion' => 'Test action',
                    'anotacion' => 'Test annotation',
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $this->processActionService->handle($this->process, $this->processId);

    $actions = ProcessAction::query()
        ->whereProcess($this->process->id)
        ->get();

    expect($actions)->toHaveCount(0);
});

it('parses dates correctly', function (): void {
    Http::fake([
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$this->processId}*" => Http::response([
            'actuaciones' => [
                [
                    'idRegActuacion' => 123456,
                    'fechaActuacion' => '2025-01-01',
                    'actuacion' => 'Test action',
                    'anotacion' => 'Test annotation',
                    'fechaInicial' => '2025-01-01',
                    'fechaFinal' => '2025-01-02',
                    'fechaRegistro' => '2025-01-01',
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $this->processActionService->handle($this->process, $this->processId);

    $action = ProcessAction::query()
        ->whereProcess($this->process->id)
        ->first();

    expect($action)->not->toBeNull();
    expect($action->action_date->format('Y-m-d'))->toBe('2025-01-01');
    expect($action->start_date?->format('Y-m-d'))->toBe('2025-01-01');
    expect($action->end_date?->format('Y-m-d'))->toBe('2025-01-02');
    expect($action->registration_date->format('Y-m-d'))->toBe('2025-01-01');
});
