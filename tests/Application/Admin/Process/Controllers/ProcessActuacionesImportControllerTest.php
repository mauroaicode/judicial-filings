<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

// ─── Helpers ────────────────────────────────────────────────────────────────

function buildActuacionesSpreadsheet(array $dataRows): Spreadsheet
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado',
        'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro',
    ];

    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    foreach ($dataRows as $rowIdx => $row) {
        $excelRow = $rowIdx + 2;
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1).$excelRow, $val);
        }
    }

    return $spreadsheet;
}

function saveSpreadsheetToTmp(Spreadsheet $spreadsheet, string $prefix = 'act-import'): string
{
    $path = tempnam(sys_get_temp_dir(), $prefix).'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

function makeUploadedFile(string $tmpPath): UploadedFile
{
    return new UploadedFile(
        $tmpPath, 'actuaciones.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null, true,
    );
}

// ─── Setup ───────────────────────────────────────────────────────────────────

beforeEach(function (): void {
    Process::query()->delete();
    ProcessImportBatch::query()->delete();

    $this->tmpPaths = [];

    $this->organization = Organization::factory()->create(['name' => 'Bufete Actuaciones Test']);

    $this->user = User::factory()->create([
        'email' => 'admin-act-import@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->ppUuid = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::PublicacionesProcesales);
});

afterEach(function (): void {
    foreach ($this->tmpPaths as $path) {
        if (is_string($path) && file_exists($path)) {
            unlink($path);
        }
    }
});

// ─── Auth ────────────────────────────────────────────────────────────────────

it('requires authentication for actuaciones import', function (): void {
    $this->postJson('/api/admin/processes/actuaciones-import', [])
        ->assertStatus(401);
});

// ─── Validation ──────────────────────────────────────────────────────────────

it('rejects request without file', function (): void {
    $this->actingAs($this->user)
        ->postJson('/api/admin/processes/actuaciones-import', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

it('rejects non-excel file', function (): void {
    $file = UploadedFile::fake()->create('actuaciones.pdf', 100, 'application/pdf');

    $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => $file])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['file']);
});

// ─── Radicado without Process → store in unassigned repository ───────────────

it('stores actuaciones in unassigned repository when radicado does not exist in db', function (): void {
    Queue::fake();

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO INEXISTENTE', '99999999900000000000001', 'Ejecutivo', 'A', 'B',
            'AUTO', '', '2026-04-30', '2026-04-30', '2026-04-24'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-unassigned');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', [
            'file' => makeUploadedFile($path),
        ]);

    $response->assertStatus(200);
    expect($response->json('unassigned_count'))->toBe(1)
        ->and($response->json('unassigned_process_numbers'))->toBe(['99999999900000000000001'])
        ->and($response->json('actions_stored_unassigned'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(0)
        ->and($response->json('processes_updated'))->toBe(0);

    expect(Process::query()->where('process_number', '99999999900000000000001')->exists())->toBeFalse();
    expect(\Src\Domain\Process\Models\UnassignedProcessAction::query()
        ->whereProcessNumber('99999999900000000000001')
        ->whereUnassigned()
        ->count())->toBe(1);
});

it('does not create a process when radicado is only stored as unassigned', function (): void {
    Queue::fake();

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO SIN REGISTRO', '11111111100000000000001', 'Verbal', 'X', 'Y',
            'ADMITE DEMANDA', '', '2026-01-10', '2026-01-10', '2026-01-09'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-nocreate');
    $this->tmpPaths[] = $path;

    $before = Process::query()->count();

    $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)])
        ->assertStatus(200);

    expect(Process::query()->count())->toBe($before);
});

it('attaches historical unassigned actuaciones when the process is created later', function (): void {
    Queue::fake();

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO RETRO', '88888888800000000000001', 'Ejecutivo', 'DEMANDANTE', 'DEMANDADO',
            'AUTO HISTORICO JULIO', 'Anota', '2026-07-10', '2026-07-10', '2026-07-09'],
        ['JUZGADO RETRO', '88888888800000000000001', 'Ejecutivo', 'DEMANDANTE', 'DEMANDADO',
            'AUTO HISTORICO AGOSTO', '', '2026-08-15', '2026-08-15', '2026-08-14'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-retro');
    $this->tmpPaths[] = $path;

    $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)])
        ->assertStatus(200)
        ->assertJsonPath('actions_stored_unassigned', 2);

    $process = Process::query()->create([
        'process_number' => '88888888800000000000001',
        'court' => 'JUZGADO RETRO',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'Ejecutivo',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2026-11-01',
        'status' => 'activo',
    ]);

    expect(ProcessAction::query()->where('process_id', $process->id)->count())->toBe(2)
        ->and(\Src\Domain\Process\Models\UnassignedProcessAction::query()
            ->whereProcessNumber('88888888800000000000001')
            ->whereUnassigned()
            ->count())->toBe(0);

    $actionIds = ProcessAction::query()->where('process_id', $process->id)->pluck('id');
    expect(\Src\Domain\Notification\Models\OrganizationNotification::query()
        ->where('notifiable_type', (new ProcessAction)->getMorphClass())
        ->whereIn('notifiable_id', $actionIds)
        ->count())->toBe(0);
});

// ─── Adds actuaciones to existing process ─────────────────────────────────────

it('adds actuaciones to an existing process found by radicado', function (): void {
    Queue::fake();

    $process = Process::query()->create([
        'process_number' => '76233408900120240006800',
        'court' => 'JUZGADO 001 PROMISCUO MUNICIPAL DE DAGUA',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'EJECUTIVO SINGULAR',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2024-05-03',
        'status' => 'activo',
    ]);
    $process->organizations()->syncWithoutDetaching([
        $this->organization->id => [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ],
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO 001 PROMISCUO MUNICIPAL DE DAGUA', '76233408900120240006800', 'EJECUTIVO SINGULAR',
            'PARCELACION CAMPESTRE ASOFLORESTA', 'SANTA MARTHA DE LEON DE CAICEDO',
            'AUTO LIBRA MANDAMIENTO DE PAGO', '', '2024-05-03', '2024-05-03', '2024-05-02'],
        ['JUZGADO 001 PROMISCUO MUNICIPAL DE DAGUA', '76233408900120240006800', 'EJECUTIVO SINGULAR',
            'PARCELACION CAMPESTRE ASOFLORESTA', 'SANTA MARTHA DE LEON DE CAICEDO',
            'AUTO ORDENA SEGUIR ADELANTE CON LA EJECUCION', '', '2025-08-21', '2025-08-21', '2025-08-20'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-existing');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)]);

    $response->assertStatus(200);
    expect($response->json('processes_updated'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(2)
        ->and($response->json('unassigned_count'))->toBe(0)
        ->and($response->json('unassigned_process_numbers'))->toBe([]);

    expect(Process::query()->where('process_number', '76233408900120240006800')->count())->toBe(1);
    expect(ProcessAction::query()->where('process_id', $process->id)->count())->toBe(2);
});

it('finds an existing process regardless of organization or data source', function (): void {
    Queue::fake();

    $judicialSourceId = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::JudicialBranch);
    $otherOrg = Organization::factory()->create(['name' => 'Otra Firma']);

    $process = Process::query()->create([
        'process_number' => '05001418902720230058700',
        'court' => 'JUZGADO 027 CIVIL MUNICIPAL DE MEDELLIN',
        'process_data_source_id' => $judicialSourceId,
        'department' => 'Antioquia',
        'process_type' => 'Proceso',
        'process_class' => 'VERBAL',
        'is_private' => false,
        'is_manual_sync' => false,
        'process_date' => '2023-01-10',
        'status' => 'activo',
    ]);
    $process->organizations()->syncWithoutDetaching([
        $otherOrg->id => [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ],
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO 027 CIVIL MUNICIPAL DE MEDELLIN', '05001418902720230058700', 'VERBAL',
            'DEMANDANTE TEST', 'DEMANDADO TEST',
            'SENTENCIA DE PRIMERA INSTANCIA', '', '2025-06-01', '2025-06-01', '2025-05-30'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-cross-org');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)]);

    $response->assertStatus(200);
    expect($response->json('processes_updated'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(1)
        ->and($response->json('unassigned_count'))->toBe(0);
});

// ─── Deduplication ───────────────────────────────────────────────────────────

it('skips actuaciones that already exist (deduplication)', function (): void {
    Queue::fake();

    $process = Process::query()->create([
        'process_number' => '76109310500320250000300',
        'court' => 'JUZGADO 003 LABORAL DEL CIRCUITO DE BUENAVENTURA',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'ORDINARIO LABORAL',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2025-03-03',
        'status' => 'activo',
    ]);

    ProcessAction::query()->create([
        'process_id' => $process->id,
        'action_registration_id' => -1,
        'cons_action' => 1,
        'action_date' => '2026-05-05',
        'action' => 'AUTO FIJA FECHA Y HORA DE AUDIENCIA',
        'annotation' => null,
        'registration_date' => '2026-05-04',
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO 003 LABORAL DEL CIRCUITO DE BUENAVENTURA', '76109310500320250000300',
            'ORDINARIO LABORAL', 'OFELIA PADILLA RODRIGUEZ Y OTROS', 'FONDO DE PASIVO SOCIAL',
            'AUTO FIJA FECHA Y HORA DE AUDIENCIA', '', '2026-05-05', '2026-05-05', '2026-05-04'],
        ['JUZGADO 003 LABORAL DEL CIRCUITO DE BUENAVENTURA', '76109310500320250000300',
            'ORDINARIO LABORAL', 'OFELIA PADILLA RODRIGUEZ Y OTROS', 'FONDO DE PASIVO SOCIAL',
            'AUTO ADMITE REFORMA A LA DEMANDA', '', '2025-12-05', '2025-12-05', '2025-12-04'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-dedup');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)]);

    $response->assertStatus(200);
    expect($response->json('actions_imported'))->toBe(1)
        ->and($response->json('actions_skipped'))->toBe(1);

    expect(ProcessAction::query()->where('process_id', $process->id)->count())->toBe(2);
});

// ─── Mixed: some found, some not ─────────────────────────────────────────────

it('imports actuaciones for existing and stores unassigned for missing', function (): void {
    Queue::fake();

    $existingProcess = Process::query()->create([
        'process_number' => '76109400300320130015400',
        'court' => 'JUZGADO 003 CIVIL MUNICIPAL DE BUENAVENTURA',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'EJECUTIVO SINGULAR',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2025-03-26',
        'status' => 'activo',
    ]);
    $existingProcess->organizations()->syncWithoutDetaching([
        $this->organization->id => [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ],
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        // Existing process
        ['JUZGADO 003 CIVIL MUNICIPAL DE BUENAVENTURA', '76109400300320130015400',
            'EJECUTIVO SINGULAR', 'EXAMIR ASPRILLA MORENO', 'MANUEL DEL CRISTO MARTINEZ',
            'SENTENCIA ANTICIPADA', '', '2025-03-26', '2025-03-26', '2025-03-25'],
        // Non-existent process
        ['JUZGADO 003 CIVIL MUNICIPAL DE BUENAVENTURA', '76109400300320180004300',
            'VERBAL SIMULACION', 'PAOLA VANESSA CHUNGA NARANJO', 'ALVARO DE JESUS RAMIREZ RIOS',
            'FIJAR NUEVAMENTE FECHA PARA DICTAR SENTENCIA', '', '2026-02-06', '2026-02-06', '2026-02-05'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-mixed');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)]);

    $response->assertStatus(200);
    expect($response->json('processes_updated'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(1)
        ->and($response->json('unassigned_count'))->toBe(1)
        ->and($response->json('unassigned_process_numbers'))->toBe(['76109400300320180004300'])
        ->and($response->json('actions_stored_unassigned'))->toBe(1);

    // Missing process must NOT be created, but actuaciones are kept in repository
    expect(Process::query()->where('process_number', '76109400300320180004300')->exists())->toBeFalse();
    expect(\Src\Domain\Process\Models\UnassignedProcessAction::query()
        ->whereProcessNumber('76109400300320180004300')
        ->count())->toBe(1);
});

// ─── Batch tracking ───────────────────────────────────────────────────────────

it('persists import batch with null organization_id on success', function (): void {
    Queue::fake();

    $process = Process::query()->create([
        'process_number' => '08001418901234567890101',
        'court' => 'JUZGADO TEST BATCH',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'Ejecutivo',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2026-04-24',
        'status' => 'activo',
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO TEST BATCH', '08001418901234567890101', 'Ejecutivo', 'A', 'B',
            'Auto Decide', 'Anotacion batch', '2026-04-30', '2026-04-30', '2026-04-24'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-batch');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)]);

    $response->assertStatus(200);
    $batchId = $response->json('import_batch_id');
    expect($batchId)->toBeString()->not->toBe('');

    $batch = ProcessImportBatch::query()->findOrFail($batchId);
    expect($batch->is_private_import)->toBeTrue()
        ->and($batch->organization_id)->toBeNull()
        ->and($batch->status)->toBe(ProcessImportBatch::STATUS_COMPLETED)
        ->and($batch->success_count)->toBe(1);
});

// ─── No organization_id or data_source_slug accepted ─────────────────────────

it('ignores organization_id and data_source_slug if sent by client', function (): void {
    Queue::fake();

    $process = Process::query()->create([
        'process_number' => '08001418901234567890199',
        'court' => 'JUZGADO TEST IGNORE',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'Ejecutivo',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2026-04-24',
        'status' => 'activo',
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO TEST IGNORE', '08001418901234567890199', 'Ejecutivo', 'A', 'B',
            'AUTO ADMITE', '', '2026-06-01', '2026-06-01', '2026-05-31'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-ignore-fields');
    $this->tmpPaths[] = $path;

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', [
            'organization_id' => $this->organization->id,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
            'file' => makeUploadedFile($path),
        ]);

    $response->assertStatus(200);
    expect($response->json('actions_imported'))->toBe(1);
});

it('queues digest notifications when importing actuaciones for an existing process', function (): void {
    Queue::fake();

    $process = Process::query()->create([
        'process_number' => '08001418901234567890888',
        'court' => 'JUZGADO DIGEST ACT',
        'process_data_source_id' => $this->ppUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'Ejecutivo',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2026-07-20',
        'status' => 'activo',
    ]);
    $process->organizations()->syncWithoutDetaching([
        $this->organization->id => [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ],
    ]);

    $spreadsheet = buildActuacionesSpreadsheet([
        ['JUZGADO DIGEST ACT', '08001418901234567890888', 'Ejecutivo', 'A', 'B',
            'AUTO ORDENA SEGUIR ADELANTE', '', '2026-07-28', '2026-07-28', '2026-07-27'],
    ]);
    $path = saveSpreadsheetToTmp($spreadsheet, 'act-digest');
    $this->tmpPaths[] = $path;

    $before = \Src\Domain\Notification\Models\OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('is_email_notified', false)
        ->count();

    $this->actingAs($this->user)
        ->post('/api/admin/processes/actuaciones-import', ['file' => makeUploadedFile($path)])
        ->assertStatus(200);

    $after = \Src\Domain\Notification\Models\OrganizationNotification::query()
        ->where('organization_id', $this->organization->id)
        ->where('is_email_notified', false)
        ->where('notifiable_type', (new ProcessAction)->getMorphClass())
        ->count();

    expect($after)->toBeGreaterThan($before);
});
