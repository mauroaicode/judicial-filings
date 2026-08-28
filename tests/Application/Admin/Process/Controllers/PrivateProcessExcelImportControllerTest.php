<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessDataSource;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    Process::query()->delete();
    ProcessImportBatch::query()->delete();

    $this->privateImportSpreadsheetTmp = null;

    $this->organization = Organization::factory()->create(['name' => 'Org Private Import Test']);

    $this->user = User::factory()->create([
        'email' => 'admin-private-import@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

afterEach(function (): void {
    $path = $this->privateImportSpreadsheetTmp ?? null;
    if (is_string($path) && $path !== '' && file_exists($path)) {
        unlink($path);
    }
});

it('requires authentication for private process import', function (): void {
    $response = $this->postJson('/api/admin/processes/private-import', []);

    $response->assertStatus(401);
});

it('imports private excel synchronously for organization', function (): void {
    Queue::fake();

    $samaiUuid = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::Samai);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Despacho',
        'Radicación',
        'Clase Proceso',
        'Demandante',
        'Demandado',
        'Actuación',
        'Anotación',
        'Fecha Inicial',
        'Fecha Finalización',
        'Fecha Registro',
    ];

    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $dataRows = [
        [
            'JUZGADO TEST',
            '08001418901234567890123',
            'Ejecutivo Singular',
            'Crediva Lores SA',
            'Ana Hernandez, Otros Demandados.',
            'Auto Decide',
            'Anotación de prueba fila 1',
            '2026-04-30',
            '2026-04-30',
            '2026-04-24',
        ],
        [
            'JUZGADO TEST',
            '08001418901234567890123',
            'Ejecutivo Singular',
            'Crediva Lores SA',
            'Ana Hernandez',
            'Auto Requie',
            '',
            '2026-04-30',
            '2026-04-30',
            '2026-04-25',
        ],
    ];

    foreach ($dataRows as $rowIdx => $row) {
        $excelRowNumber = $rowIdx + 2;
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1).$excelRowNumber, $val);
        }
    }

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $uploadedFile = new UploadedFile(
        $this->privateImportSpreadsheetTmp,
        'private-rows.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $uploadedFile,
            'data_source_slug' => ProcessDataSourceSlug::Samai->value,
        ]);

    $response->assertStatus(200);
    expect($response->json('message'))->not->toBeEmpty()
        ->and($response->json('processes_created'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(2);

    $importBatchId = $response->json('import_batch_id');
    expect($importBatchId)->toBeString()->not->toBe('');
    /** @var ProcessImportBatch $batch */
    $batch = ProcessImportBatch::query()->findOrFail($importBatchId);
    expect($batch->is_private_import)->toBeTrue()
        ->and($batch->organization_id)->toBe($this->organization->id)
        ->and((string) $batch->requested_by)->toBe((string) $this->user->id)
        ->and($batch->status)->toBe(ProcessImportBatch::STATUS_COMPLETED)
        ->and($batch->file_name)->toBe('private-rows.xlsx')
        ->and($batch->excel_total_count)->toBe(2)
        ->and($batch->total_count)->toBe(1)
        ->and($batch->success_count)->toBe(2)
        ->and($batch->failed_count)->toBe(0)
        ->and($batch->multiple_instances_count)->toBe(0)
        ->and($batch->laravel_batch_id)->toBeNull();

    expect(Process::query()->where('process_data_source_id', $samaiUuid)->count())->toBe(1);
    expect(ProcessAction::query()->count())->toBe(2);

    $process = Process::query()->first();
    expect($process)->not->toBeNull()
        ->and($process->is_private)->toBeTrue()
        ->and($process->is_manual_sync)->toBeTrue();

    expect($process->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue();

    $actions = ProcessAction::query()->orderBy('cons_action')->get();
    expect($actions)->toHaveCount(2)
        ->and($actions[0]->annotation)->toBe('Anotacion de Prueba Fila 1')
        ->and($actions[1]->annotation)->toBeNull();
});

it('persists failed import batch when spreadsheet has validation errors', function (): void {
    Queue::fake();

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach (
        ['Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado', 'Actuación'] as $i => $title
    ) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'JUZ');
    $sheet->setCellValue('B2', 'SHORT');
    $sheet->setCellValue('C2', 'Ejecutivo');
    $sheet->setCellValue('F2', 'Acto');

    $tmpPath = tempnam(sys_get_temp_dir(), 'private-import-bad').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmpPath);

    $uploadedFile = new UploadedFile(
        $tmpPath,
        'bad-private.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    try {
        $response = $this->actingAs($this->user)
            ->post('/api/admin/processes/private-import', [
                'organization_id' => $this->organization->id,
                'file' => $uploadedFile,
                'data_source_slug' => ProcessDataSourceSlug::Samai->value,
            ]);

        $response->assertStatus(422);

        $importBatchId = $response->json('import_batch_id');
        expect($importBatchId)->toBeString()->not->toBe('');
        /** @var ProcessImportBatch $batch */
        $batch = ProcessImportBatch::query()->findOrFail($importBatchId);
        expect($batch->is_private_import)->toBeTrue()
            ->and($batch->status)->toBe(ProcessImportBatch::STATUS_FAILED)
            ->and($batch->excel_total_count)->toBe(0)
            ->and($batch->total_count)->toBe(0)
            ->and($batch->failed_count)->toBeGreaterThan(0);

        expect(Process::query()->count())->toBe(0);
    } finally {
        if (file_exists($tmpPath)) {
            unlink($tmpPath);
        }
    }
});

it('imports private excel with publicaciones_procesales data source', function (): void {
    Queue::fake();

    $sourceUuid = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::PublicacionesProcesales);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    $headers = [
        'Despacho',
        'Radicación',
        'Clase Proceso',
        'Demandante',
        'Demandado',
        'Actuación',
        'Anotación',
        'Fecha Inicial',
        'Fecha Finalización',
        'Fecha Registro',
    ];

    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'JUZGADO PROMISCUO MUNICIPAL DE HATO COROZAL');
    $sheet->setCellValue('B2', '08001418901220220076400');
    $sheet->setCellValue('C2', 'Ejecutivo Singular');
    $sheet->setCellValue('D2', 'Credivalores');
    $sheet->setCellValue('E2', 'Vicente Alberto Diaz Pedroza');
    $sheet->setCellValue('F2', 'Auto Decidir');
    $sheet->setCellValue('G2', 'Ordena seguir adelante la ejecución');
    $sheet->setCellValue('H2', '2026-04-30');
    $sheet->setCellValue('I2', '2026-04-30');
    $sheet->setCellValue('J2', '2026-04-24');

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-pp').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $uploadedFile = new UploadedFile(
        $this->privateImportSpreadsheetTmp,
        'plantilla-importar-privados.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $uploadedFile,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ]);

    $response->assertStatus(200);
    expect($response->json('processes_created'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(1);

    $process = Process::query()->first();
    expect($process)->not->toBeNull()
        ->and($process->process_data_source_id)->toBe($sourceUuid)
        ->and($process->process_number)->toBe('08001418901220220076400')
        ->and($process->is_private)->toBeTrue()
        ->and($process->is_manual_sync)->toBeTrue()
        ->and($process->processDataSource?->slug)->toBe(ProcessDataSourceSlug::PublicacionesProcesales->value);
});

it('defaults private import data source to publicaciones_procesales when omitted', function (): void {
    Queue::fake();

    $sourceUuid = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::PublicacionesProcesales);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    foreach (
        ['Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado', 'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro'] as $i => $title
    ) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'JUZGADO TEST DEFAULT');
    $sheet->setCellValue('B2', '08001418901234567890124');
    $sheet->setCellValue('C2', 'Ejecutivo');
    $sheet->setCellValue('D2', 'Demandante SA');
    $sheet->setCellValue('E2', 'Demandado');
    $sheet->setCellValue('F2', 'Auto');
    $sheet->setCellValue('G2', '');
    $sheet->setCellValue('H2', '2026-04-30');
    $sheet->setCellValue('I2', '2026-04-30');
    $sheet->setCellValue('J2', '2026-04-24');

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-default').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $uploadedFile = new UploadedFile(
        $this->privateImportSpreadsheetTmp,
        'default-source.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $uploadedFile,
        ]);

    $response->assertStatus(200);

    $process = Process::query()->first();
    expect($process)->not->toBeNull()
        ->and($process->process_data_source_id)->toBe($sourceUuid);
});

it('imports private process without actuación or dates', function (): void {
    Queue::fake();

    $sourceUuid = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::PublicacionesProcesales);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    foreach (
        ['Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado', 'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro'] as $i => $title
    ) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'JUZGADO 001 PROMISCUO MUNICIPAL DE DAGUA');
    $sheet->setCellValue('B2', '76233408900120230011400');
    $sheet->setCellValue('C2', 'VERBAL PERTENENCIA (LEY 1561 DE 2012)');
    $sheet->setCellValue('D2', 'CARMEN ELENA MEDINA MUÑOZ');
    $sheet->setCellValue('E2', 'HEREDEROS DETERMINADOS E INDETERMINADOS');
    $sheet->setCellValue('F2', '');
    $sheet->setCellValue('G2', '');
    $sheet->setCellValue('H2', '');
    $sheet->setCellValue('I2', '');
    $sheet->setCellValue('J2', '');

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-no-act').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $uploadedFile = new UploadedFile(
        $this->privateImportSpreadsheetTmp,
        'sin-actuacion.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $uploadedFile,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ]);

    $response->assertStatus(200);
    expect($response->json('processes_created'))->toBe(1)
        ->and($response->json('actions_imported'))->toBe(0);

    $process = Process::query()->first();
    expect($process)->not->toBeNull()
        ->and($process->process_data_source_id)->toBe($sourceUuid)
        ->and($process->process_number)->toBe('76233408900120230011400')
        ->and($process->court)->toBe('Juzgado 001 Promiscuo Municipal de Dagua')
        ->and($process->process_class)->toBe('Verbal Pertenencia (ley 1561 de 2012)')
        ->and(ProcessAction::query()->count())->toBe(0);

    $process->load('subjects');
    expect($process->subjects)->not->toBeEmpty();

    $plaintiff = $process->subjects->firstWhere('subject_type', 'Demandante');
    expect($plaintiff)->not->toBeNull()
        ->and($plaintiff->name_or_business_name)->toBe('Carmen Elena Medina Muñoz');
});

it('rejects judicial_branch as data_source_slug', function (): void {
    Queue::fake();

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'Despacho');
    $sheet->setCellValue('B1', 'Radicación');
    $sheet->setCellValue('C1', 'Clase Proceso');
    $sheet->setCellValue('G1', 'Actuación');

    $tmp = tempnam(sys_get_temp_dir(), 'private-import-branch').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmp);

    try {
        $uploadedFile = new UploadedFile(
            $tmp,
            'bad.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->user)
            ->post('/api/admin/processes/private-import', [
                'organization_id' => $this->organization->id,
                'file' => $uploadedFile,
                'data_source_slug' => ProcessDataSourceSlug::JudicialBranch->value,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['data_source_slug']);
    } finally {
        if (file_exists($tmp)) {
            unlink($tmp);
        }
    }
});

it('splits combined fijacion estado + auto into two actuaciones on private import', function (): void {
    Queue::fake();

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    foreach (
        ['Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado', 'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro'] as $i => $title
    ) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'JUZGADO 001 PROMISCUO MUNICIPAL DE DAGUA');
    $sheet->setCellValue('B2', '76233408900120260014600');
    $sheet->setCellValue('C2', 'Pertenencia');
    $sheet->setCellValue('D2', 'NORBERTO MUÑOZ VALENCIA');
    $sheet->setCellValue('E2', 'HEREDEROS');
    $sheet->setCellValue('F2', 'Fijacion Estado Auto Admite Demanda');
    $sheet->setCellValue('G2', '');
    $sheet->setCellValue('H2', '2026-04-27');
    $sheet->setCellValue('I2', '2026-04-27');
    $sheet->setCellValue('J2', '2026-04-24');

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-fijacion').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $uploadedFile = new UploadedFile(
        $this->privateImportSpreadsheetTmp,
        'fijacion-split.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $uploadedFile,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ]);

    $response->assertStatus(200);
    expect($response->json('actions_imported'))->toBe(2);

    $actions = ProcessAction::query()->orderBy('cons_action')->get();
    expect($actions)->toHaveCount(2)
        ->and($actions[0]->action)->toBe('Fijación Estado')
        ->and($actions[1]->action)->toBe('Auto Admite Demanda')
        ->and($actions[0]->registration_date->format('Y-m-d'))->toBe('2026-04-24')
        ->and($actions[1]->registration_date->format('Y-m-d'))->toBe('2026-04-24');
});

it('does not queue digest notifications when creating processes via private import', function (): void {
    Queue::fake();

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    foreach (
        ['Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado', 'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro'] as $i => $title
    ) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $sheet->setCellValue('A2', 'JUZGADO SIN DIGEST');
    $sheet->setCellValue('B2', '08001418901234567890999');
    $sheet->setCellValue('C2', 'Ejecutivo');
    $sheet->setCellValue('D2', 'A');
    $sheet->setCellValue('E2', 'B');
    $sheet->setCellValue('F2', 'AUTO ADMITE DEMANDA');
    $sheet->setCellValue('G2', '');
    $sheet->setCellValue('H2', '2026-07-28');
    $sheet->setCellValue('I2', '2026-07-28');
    $sheet->setCellValue('J2', '2026-07-27');

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-no-digest').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $uploadedFile = new UploadedFile(
        $this->privateImportSpreadsheetTmp,
        'no-digest.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );

    $before = \Src\Domain\Notification\Models\OrganizationNotification::query()->count();

    $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $uploadedFile,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ])
        ->assertStatus(200);

    expect(ProcessAction::query()->whereHas('process', fn ($q) => $q->where('process_number', '08001418901234567890999'))->count())->toBe(1)
        ->and(\Src\Domain\Notification\Models\OrganizationNotification::query()->count())->toBe($before);
});

it('attaches publicaciones procesales actuaciones to an existing laboral from Unificada', function (): void {
    Queue::fake();

    $processNumber = '76520310500320260013300';
    $existing = Process::factory()->create([
        'process_number' => $processNumber,
        'court' => 'Juzgado 003 Laboral del Circuito de Palmira',
        'process_data_source_id' => ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::JudicialBranch),
        'is_manual_sync' => false,
        'is_private' => false,
    ]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $headers = [
        'Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado',
        'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro',
    ];
    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }
    $sheet->setCellValue('A2', 'Juzgado 003 Laboral del Circuito de Palmira');
    $sheet->setCellValueExplicit('B2', $processNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $sheet->setCellValue('C2', 'Ordinario Laboral');
    $sheet->setCellValue('D2', 'Demandante');
    $sheet->setCellValue('E2', 'Demandado');
    $sheet->setCellValue('F2', 'Constancia secretarial');
    $sheet->setCellValue('H2', '2026-08-12');
    $sheet->setCellValue('I2', '2026-08-12');
    $sheet->setCellValue('J2', '2026-08-12');

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-laboral').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => new UploadedFile(
                $this->privateImportSpreadsheetTmp,
                'laboral.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('processes_created', 0)
        ->assertJsonPath('processes_updated', 1)
        ->assertJsonPath('actions_imported', 1);

    expect(Process::query()->where('process_number', $processNumber)->count())->toBe(1)
        ->and(Process::query()->find($existing->id)?->process_data_source_id)
        ->toBe(ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::JudicialBranch))
        ->and(ProcessAction::query()->where('process_id', $existing->id)->count())->toBe(1)
        ->and($existing->organizations()->where('organizations.id', $this->organization->id)->exists())->toBeTrue();
});

it('does not re-import sujetos when the radicado already exists in an organization', function (): void {
    Queue::fake();

    $sourceUuid = ProcessDataSource::uuidForSlug(ProcessDataSourceSlug::PublicacionesProcesales);
    $processNumber = '76233408900120240007000';

    $process = Process::query()->create([
        'process_number' => $processNumber,
        'court' => 'Juzgado 001 Promiscuo Municipal de Dagua',
        'process_data_source_id' => $sourceUuid,
        'department' => 'Sin departamento',
        'process_type' => 'Proceso privado',
        'process_class' => 'Ejecutivo Singular',
        'litigants' => 'Demandante: ORIGINAL PLAINTIFF | Demandado: ORIGINAL DEFENDANT',
        'is_private' => true,
        'is_manual_sync' => true,
        'process_date' => '2024-05-03',
        'status' => 'activo',
    ]);
    $process->organizations()->syncWithoutDetaching([
        $this->organization->id => [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => \Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus::ACTIVE->value,
        ],
    ]);

    $existingPlaintiff = \Src\Domain\Process\Models\ProcessSubject::query()->create([
        'subject_registration_id' => null,
        'subject_type' => \Src\Domain\Process\Models\ProcessSubject::TYPE_PLAINTIFF,
        'is_cited' => false,
        'identification' => null,
        'name_or_business_name' => 'ORIGINAL PLAINTIFF',
    ]);
    $existingDefendant = \Src\Domain\Process\Models\ProcessSubject::query()->create([
        'subject_registration_id' => null,
        'subject_type' => \Src\Domain\Process\Models\ProcessSubject::TYPE_DEFENDANT,
        'is_cited' => false,
        'identification' => null,
        'name_or_business_name' => 'ORIGINAL DEFENDANT',
    ]);
    $process->subjects()->attach([$existingPlaintiff->id, $existingDefendant->id]);

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $headers = [
        'Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado',
        'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro',
    ];
    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $rows = [
        ['Libra mandamiento de pago', 'ORIGINAL PLAINTIFF', 'ORIGINAL DEFENDANT'],
        ['Decreta medida cautelar', 'ORIGINAL PLAINTIFF', 'ORIGINAL DEFENDANT'],
        ['Auto extra', 'ORIGINAL PLAINTIFF', 'ORIGINAL DEFENDANT'],
    ];
    foreach ($rows as $idx => [$action, $plaintiff, $defendant]) {
        $excelRow = $idx + 2;
        $sheet->setCellValue('A'.$excelRow, 'Juzgado 001 Promiscuo Municipal de Dagua');
        $sheet->setCellValueExplicit('B'.$excelRow, $processNumber, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue('C'.$excelRow, 'Ejecutivo Singular');
        $sheet->setCellValue('D'.$excelRow, $plaintiff);
        $sheet->setCellValue('E'.$excelRow, $defendant);
        $sheet->setCellValue('F'.$excelRow, $action);
        $sheet->setCellValue('H'.$excelRow, '2024-06-0'.($idx + 1));
        $sheet->setCellValue('I'.$excelRow, '2024-06-0'.($idx + 1));
        $sheet->setCellValue('J'.$excelRow, '2024-06-0'.($idx + 1));
    }

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-no-subjects').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => new UploadedFile(
                $this->privateImportSpreadsheetTmp,
                'actuaciones.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('processes_created', 0)
        ->assertJsonPath('processes_updated', 1)
        ->assertJsonPath('actions_imported', 3);

    $process->refresh()->load('subjects');

    expect($process->subjects)->toHaveCount(2)
        ->and($process->subjects->pluck('name_or_business_name')->all())->toEqualCanonicalizing([
            'ORIGINAL PLAINTIFF',
            'ORIGINAL DEFENDANT',
        ])
        ->and($process->litigants)->toBe('Demandante: ORIGINAL PLAINTIFF | Demandado: ORIGINAL DEFENDANT')
        ->and(ProcessAction::query()->where('process_id', $process->id)->count())->toBe(3);
});

it('creates separate processes when the same radicado and court have different parties in one upload', function (): void {
    Queue::fake();

    $processNumber = '19743408900220250003300';
    $court = 'Juzgado 002 Promiscuo Municipal de Sutatenza';
    $defendant = 'PARROCO CEMENTERIO MUNICIPIO SUTATENZA';

    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $headers = [
        'Despacho', 'Radicación', 'Clase Proceso', 'Demandante', 'Demandado',
        'Actuación', 'Anotación', 'Fecha Inicial', 'Fecha Finalización', 'Fecha Registro',
    ];
    foreach ($headers as $i => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'1', $title);
    }

    $dataRows = [
        [$court, $processNumber, 'Ejecutivo a continuación', 'GERARDO HERRERA', $defendant, 'Libra mandamiento de pago', '', '2026-08-27', '2026-08-27', '2026-08-27'],
        [$court, $processNumber, 'Ejecutivo a continuación', 'GERARDO HERRERA', $defendant, 'Decreta medida cautelar', '', '2026-08-27', '2026-08-27', '2026-08-27'],
        [$court, $processNumber, 'Ejecutivo a continuación', 'WILLMAR ARIEL PERALTA MORENO', $defendant, 'Libra mandamiento de pago', '', '2026-08-27', '2026-08-27', '2026-08-27'],
        [$court, $processNumber, 'Ejecutivo a continuación', 'WILLMAR ARIEL PERALTA MORENO', $defendant, 'Decreta medida cautelar', '', '2026-08-27', '2026-08-27', '2026-08-27'],
    ];

    foreach ($dataRows as $rowIdx => $row) {
        $excelRow = $rowIdx + 2;
        foreach ($row as $colIdx => $val) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIdx + 1).$excelRow, $val);
        }
    }

    $this->privateImportSpreadsheetTmp = tempnam(sys_get_temp_dir(), 'private-import-same-rad-parties').'.xlsx';
    (new Xlsx($spreadsheet))->save($this->privateImportSpreadsheetTmp);

    $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => new UploadedFile(
                $this->privateImportSpreadsheetTmp,
                'same-rad-parties.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ])
        ->assertStatus(200)
        ->assertJsonPath('processes_created', 2)
        ->assertJsonPath('processes_updated', 0)
        ->assertJsonPath('actions_imported', 4);

    $processes = Process::query()
        ->where('process_number', $processNumber)
        ->where('court', $court)
        ->with('subjects')
        ->get();

    expect($processes)->toHaveCount(2);

    $gerardo = $processes->first(
        fn (Process $process): bool => $process->subjects->contains(
            fn ($subject): bool => $subject->name_or_business_name === 'GERARDO HERRERA'
        )
    );
    $wilmar = $processes->first(
        fn (Process $process): bool => $process->subjects->contains(
            fn ($subject): bool => $subject->name_or_business_name === 'WILLMAR ARIEL PERALTA MORENO'
        )
    );

    expect($gerardo)->not->toBeNull()
        ->and($wilmar)->not->toBeNull()
        ->and(ProcessAction::query()->where('process_id', $gerardo->id)->pluck('action')->all())
        ->toEqualCanonicalizing(['Libra mandamiento de pago', 'Decreta medida cautelar'])
        ->and(ProcessAction::query()->where('process_id', $wilmar->id)->pluck('action')->all())
        ->toEqualCanonicalizing(['Libra mandamiento de pago', 'Decreta medida cautelar']);
});
