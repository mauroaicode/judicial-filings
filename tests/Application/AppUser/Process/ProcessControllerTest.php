<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    // Attach user to organization
    $this->appUser->organizations()->attach($this->organization->id, [
        'is_owner' => true,
    ]);
});

it('requires authentication to register a process', function (): void {
    $response = $this->postJson('/api/app-user/processes', [
        'process_number' => '76001333301320170009301',
    ]);

    $response->assertStatus(401);
});

it('validates that process_number is required', function (): void {
    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_number']);
});

it('validates that process_number has exactly 23 digits', function (): void {
    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => '123456789012345678901', // 21 digits
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_number']);
});

it('validates that process_number contains only digits', function (): void {
    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => '7600133330132017000930A', // contains letter
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['process_number']);
});

it('rejects process registration when user has no organization', function (): void {
    $userWithoutOrg = AppUser::factory()->create([
        'email' => 'noorg@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);

    Http::fake([
        '*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => 1834511724,
                ],
            ],
        ], 200),
    ]);

    $response = $this->actingAs($userWithoutOrg)
        ->postJson('/api/app-user/processes', [
            'process_number' => '76001333301320170009301',
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.user_has_no_organization')],
    ]);
});

it('rejects process registration when radicado does not exist in judicial branch', function (): void {
    Http::fake([
        '*' => Http::response([
            'procesos' => [],
        ], 200),
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => '76001333301320170009301',
        ]);

    $response->assertStatus(404);
    $response->assertJson([
        'messages' => [__('process.not_found_in_judicial_branch')],
    ]);
});

it('rejects process registration when radicado is already registered for organization', function (): void {
    $process = Process::factory()->create([
        'process_number' => '76001333301320170009301',
    ]);

    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => '76001333301320170009301',
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.already_registered')],
    ]);
});

it('registers a new process successfully', function (): void {
    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => 1834511724,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url').'/Proceso/Detalle/1834511724' => Http::response([
            'idProceso' => 1834511724,
            'despacho' => 'DESPACHO 000 - TRIBUNAL ADMINISTRATIVO',
            'departamento' => 'VALLE DEL CAUCA',
            'tipoProceso' => 'Ordinario',
            'claseProceso' => 'ACCION DE REPARACION DIRECTA',
            'subclaseProceso' => 'Sin Subclase de Proceso',
            'sujetosProcesales' => 'Test litigants',
            'fechaProceso' => '2024-12-06',
            'fechaUltimaActuacion' => '2025-04-30',
            'ubicacion' => 'Despacho',
            'contenidoRadicacion' => 'APELACION DE SENTENCIA',
            'esPrivado' => false,
        ], 200),
        config('judicial-branch.api_url').'/Proceso/Actuaciones/1834511724*' => Http::response([
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
        config('judicial-branch.api_url').'/Proceso/Sujetos/1834511724*' => Http::response([
            'sujetos' => [
                [
                    'idRegSujeto' => 14585521,
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

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => '76001333301320170009301',
        ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'message',
        'process' => [
            'id',
            'process_number',
            'court',
        ],
    ]);

    expect($response->json('process.process_number'))->toBe('76001333301320170009301');
    expect($response->json('message'))->toBe(__('process.registered_successfully'));

    // Verify process was created
    $process = Process::query()
        ->where('process_number', '76001333301320170009301')
        ->first();

    expect($process)->not->toBeNull();
    expect($process->process_id)->toBe(1834511724);
    expect($process->court)->toContain('DESPACHO 000 - TRIBUNAL ADMINISTRATIVO');

    // Verify process is attached to organization
    expect($process->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue();
});

it('attaches existing process to organization if process already exists globally', function (): void {
    // Use unique process_number and process_id to avoid conflicts with other tests
    // Generate a truly unique 23-digit process number
    do {
        $timestamp = (int) (microtime(true) * 1000); // milliseconds since epoch
        $random = random_int(100000, 999999);
        $uniqueProcessNumber = str_pad((string) ($timestamp + $random), 23, '0', STR_PAD_LEFT);

        // Ensure it's exactly 23 digits
        if (strlen($uniqueProcessNumber) > 23) {
            $uniqueProcessNumber = substr($uniqueProcessNumber, -23);
        }

        // Check if this number is already used by this organization
        $existsForOrg = Process::query()
            ->whereProcessNumber($uniqueProcessNumber)
            ->whereHas('organizations', function ($query): void {
                $query->where('organizations.id', $this->organization->id);
            })
            ->exists();
    } while ($existsForOrg);

    $existingProcessId = random_int(9000000000, 9999999999);

    // Get or create the global process
    $existingProcess = Process::query()
        ->whereProcessNumber($uniqueProcessNumber)
        ->first();

    if (! $existingProcess) {
        $existingProcess = Process::factory()->create([
            'process_number' => $uniqueProcessNumber,
            'process_id' => $existingProcessId,
        ]);
    } else {
        // Update process_id if it doesn't match
        if ($existingProcess->process_id !== $existingProcessId) {
            $existingProcess->update(['process_id' => $existingProcessId]);
        }
    }

    // Ensure the process is not already attached to ANY organization (including this one)
    // This is important because the process might be attached to another organization from a previous test
    $existingProcess->organizations()->detach();
    $existingProcess->refresh();

    // Double-check that the process is not attached to this organization
    expect($existingProcess->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeFalse();

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $existingProcessId,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$existingProcessId}" => Http::response([
            'idProceso' => $existingProcessId,
            'despacho' => 'JUZGADO 035 CIVIL MUNICIPAL DE BOGOTÁ',
            'departamento' => 'BOGOTÁ',
            'tipoProceso' => 'Ordinario',
            'claseProceso' => 'ACCION DE REPARACION DIRECTA',
            'subclaseProceso' => 'Sin Subclase de Proceso',
            'sujetosProcesales' => 'Test litigants',
            'fechaProceso' => '2016-09-14',
            'fechaUltimaActuacion' => '2025-08-05',
            'ubicacion' => 'Despacho',
            'contenidoRadicacion' => 'Test content',
            'esPrivado' => false,
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$existingProcessId}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$existingProcessId}*" => Http::response([
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

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $uniqueProcessNumber,
        ]);

    $response->assertStatus(201);

    // Verify process is attached to organization
    expect($existingProcess->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue();
});
