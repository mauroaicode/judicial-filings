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
