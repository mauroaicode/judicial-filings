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

it('rejects process registration when all instances are private', function (): void {
    $processId1 = random_int(2000000000, 2999999999);
    $processId2 = random_int(3000000000, 3999999999);
    $processNumber = '12345678901234567890123'; // Unique 23-digit number

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId1,
                    'esPrivado' => true,
                ],
                [
                    'idProceso' => $processId2,
                    'esPrivado' => true,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.all_instances_are_private')],
    ]);

    // Verify no processes were created
    $processes = Process::query()
        ->where('process_number', $processNumber)
        ->get();

    expect($processes)->toHaveCount(0);
});

it('rejects process registration when existing global process is private', function (): void {
    $processId = random_int(3000000000, 3999999999);
    $processNumber = '98765432109876543210987'; // Unique 23-digit number

    $existingProcess = Process::factory()->create([
        'process_id' => $processId,
        'process_number' => $processNumber,
        'is_private' => true,
    ]);

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId,
                    'esPrivado' => false,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$processId}" => Http::response([
            'idProceso' => $processId,
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
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
        ]);

    $response->assertStatus(422);
    // When there's a single instance and the existing global process is private, return is_private message
    $response->assertJson([
        'messages' => [__('process.is_private')],
    ]);

    // Verify process was not attached to organization
    expect($existingProcess->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeFalse();
});

it('rejects process registration when single instance is private', function (): void {
    $processId = random_int(4000000000, 4999999999);
    $processNumber = '11111111111111111111111'; // Unique 23-digit number

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId,
                    'esPrivado' => true,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => $processNumber,
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'messages' => [__('process.is_private')],
    ]);

    // Verify no process was created
    $process = Process::query()
        ->where('process_number', $processNumber)
        ->first();

    expect($process)->toBeNull();
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
        'has_multiple_instances',
        'total_processes',
        'registered_count',
        'private_count',
        'process' => [
            'id',
            'process_number',
            'court',
            'term_start_date',
            'term_end_date',
        ],
    ]);

    expect($response->json('process.process_number'))->toBe('76001333301320170009301');
    expect($response->json('message'))->toBe(__('process.registered_successfully'));
    expect($response->json('has_multiple_instances'))->toBeFalse();
    expect($response->json('total_processes'))->toBe(1);
    expect($response->json('registered_count'))->toBe(1);
    expect($response->json('private_count'))->toBe(0);

    // Verify process was created (use response id so we assert on the process just registered)
    $processUuid = $response->json('process.id');
    $process = Process::query()->find($processUuid);

    expect($process)->not->toBeNull()
        ->and($process->process_id)->toBe(1834511724)
        ->and($process->court)->toContain('DESPACHO 000 - TRIBUNAL ADMINISTRATIVO')
        ->and($process->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue();
});

it('attaches existing process to organization if process already exists globally', function (): void {
    // Use a different process_id to avoid conflicts
    $existingProcessId = random_int(1000000000, 1999999999);
    $existingProcess = Process::factory()->create([
        'process_number' => '76001333301320170009301',
        'process_id' => $existingProcessId,
        'is_private' => false,
    ]);

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $existingProcessId,
                    'esPrivado' => false,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$existingProcessId}" => Http::response([
            'idProceso' => $existingProcessId,
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
    ]);

    $response = $this->actingAs($this->appUser)
        ->postJson('/api/app-user/processes', [
            'process_number' => '76001333301320170009301',
        ]);

    $response->assertStatus(201);

    // Verify process is attached to organization
    expect($existingProcess->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue();
});

it('registers multiple instances successfully', function (): void {
    $processId1 = random_int(4000000000, 4999999999);
    $processId2 = random_int(5000000000, 5999999999);
    $processNumber = '11001400303520160089003'; // Unique number

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId1,
                    'esPrivado' => false,
                ],
                [
                    'idProceso' => $processId2,
                    'esPrivado' => false,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$processId1}" => Http::response([
            'idProceso' => $processId1,
            'despacho' => 'JUZGADO 035 CIVIL MUNICIPAL DE BOGOTÁ',
            'departamento' => 'BOGOTÁ',
            'tipoProceso' => 'Ordinario',
            'claseProceso' => 'ACCION DE REPARACION DIRECTA',
            'subclaseProceso' => 'Sin Subclase de Proceso',
            'sujetosProcesales' => 'Test litigants 1',
            'fechaProceso' => '2016-09-14',
            'fechaUltimaActuacion' => '2025-08-05',
            'ubicacion' => 'Despacho',
            'contenidoRadicacion' => 'Test content',
            'esPrivado' => false,
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$processId2}" => Http::response([
            'idProceso' => $processId2,
            'despacho' => 'JUZGADO 035 CIVIL MUNICIPAL DE BOGOTÁ',
            'departamento' => 'BOGOTÁ',
            'tipoProceso' => 'Ordinario',
            'claseProceso' => 'ACCION DE REPARACION DIRECTA',
            'subclaseProceso' => 'Sin Subclase de Proceso',
            'sujetosProcesales' => 'Test litigants 2',
            'fechaProceso' => '2016-09-15',
            'fechaUltimaActuacion' => '2025-08-06',
            'ubicacion' => 'Despacho',
            'contenidoRadicacion' => 'Test content 2',
            'esPrivado' => false,
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId1}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId2}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$processId1}*" => Http::response([
            'sujetos' => [],
            'paginacion' => [
                'cantidadRegistros' => 0,
                'registrosPagina' => 40,
                'cantidadPaginas' => 1,
                'pagina' => 1,
                'paginas' => null,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$processId2}*" => Http::response([
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
            'process_number' => $processNumber,
        ]);

    $response->assertStatus(201);
    expect($response->json('has_multiple_instances'))->toBeTrue();
    expect($response->json('total_processes'))->toBe(2);
    expect($response->json('registered_count'))->toBe(2);
    expect($response->json('private_count'))->toBe(0);
    expect($response->json('message'))->toContain('2 proceso(s)');

    // Verify both processes were created
    $processes = Process::query()
        ->where('process_number', $processNumber)
        ->get();

    expect($processes)->toHaveCount(2);
    expect($processes->pluck('has_multiple_instances')->unique())->toContain(true);
});

it('registers multiple instances with some private processes', function (): void {
    $processId1 = random_int(6000000000, 6999999999);
    $processId2 = random_int(7000000000, 7999999999);
    $processNumber = '11001400303520160089004'; // Unique number

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId1,
                    'esPrivado' => false,
                ],
                [
                    'idProceso' => $processId2,
                    'esPrivado' => true,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$processId1}" => Http::response([
            'idProceso' => $processId1,
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
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId1}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$processId1}*" => Http::response([
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
            'process_number' => $processNumber,
        ]);

    $response->assertStatus(201);
    expect($response->json('has_multiple_instances'))->toBeTrue();
    expect($response->json('total_processes'))->toBe(2);
    expect($response->json('registered_count'))->toBe(1);
    expect($response->json('private_count'))->toBe(1);
    expect($response->json('message'))->toContain('1 proceso es privado');

    // Verify only one process was created
    $processes = Process::query()
        ->where('process_number', $processNumber)
        ->get();

    expect($processes)->toHaveCount(1);
});

it('registers multiple instances with multiple private processes', function (): void {
    $processId1 = random_int(8000000000, 8999999999);
    $processId2 = random_int(9000000000, 9999999999);
    $processId3 = random_int(1000000000, 1999999999);
    $processNumber = '11001400303520160089005'; // Unique number

    Http::fake([
        config('judicial-branch.api_url').'/Procesos/Consulta/NumeroRadicacion*' => Http::response([
            'procesos' => [
                [
                    'idProceso' => $processId1,
                    'esPrivado' => false,
                ],
                [
                    'idProceso' => $processId2,
                    'esPrivado' => true,
                ],
                [
                    'idProceso' => $processId3,
                    'esPrivado' => true,
                ],
            ],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Detalle/{$processId1}" => Http::response([
            'idProceso' => $processId1,
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
        config('judicial-branch.api_url')."/Proceso/Actuaciones/{$processId1}*" => Http::response([
            'actuaciones' => [],
            'paginacion' => [
                'cantidadPaginas' => 1,
            ],
        ], 200),
        config('judicial-branch.api_url')."/Proceso/Sujetos/{$processId1}*" => Http::response([
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
            'process_number' => $processNumber,
        ]);

    $response->assertStatus(201);
    expect($response->json('has_multiple_instances'))->toBeTrue();
    expect($response->json('total_processes'))->toBe(3);
    expect($response->json('registered_count'))->toBe(1);
    expect($response->json('private_count'))->toBe(2);
    expect($response->json('message'))->toContain('2 procesos son privados');

    // Verify only one process was created
    $processes = Process::query()
        ->where('process_number', $processNumber)
        ->get();

    expect($processes)->toHaveCount(1);
});
