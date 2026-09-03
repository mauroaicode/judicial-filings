<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Excel;
use Maatwebsite\Excel\Facades\Excel as ExcelFacade;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Src\Application\Shared\Notifications\ProcessImportReportNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\Process\Enums\ProcessDataSourceSlug;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessImportBatch;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    global $quotaImportBulkTmpFiles;

    ProcessImportBatch::query()->delete();
    Process::query()->delete();

    $quotaImportBulkTmpFiles = [];
    $this->privateImportSpreadsheetTmp = null;

    config(['organization.defaults.max_active_processes' => null]);

    $this->organization = Organization::factory()->create(['name' => 'Org Quota Import Test', 'is_active' => true]);

    OrganizationSetting::factory()->create([
        'organization_id' => $this->organization->id,
        'max_active_processes' => 2,
    ]);

    $this->user = User::factory()->create([
        'email' => 'admin-quota-import@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);
});

afterEach(function (): void {
    global $quotaImportBulkTmpFiles;

    foreach ($quotaImportBulkTmpFiles as $filename) {
        Storage::disk('local')->delete($filename);
    }

    $privatePath = $this->privateImportSpreadsheetTmp ?? null;
    if (is_string($privatePath) && $privatePath !== '' && file_exists($privatePath)) {
        unlink($privatePath);
    }
});

/**
 * @param  list<string>  $processNumbers
 */
function attachActiveRadicadoForQuotaImportTest(Organization $organization, array $processNumbers): void
{
    foreach ($processNumbers as $processNumber) {
        $process = Process::factory()->create(['process_number' => $processNumber]);
        $process->organizations()->attach($organization->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => OrganizationProcessStatus::ACTIVE->value,
        ]);
    }
}

/** @var list<string> */
$quotaImportBulkTmpFiles = [];

/**
 * @param  list<string>  $processNumbers
 */
function makeBulkImportExcelUpload(array $processNumbers): UploadedFile
{
    global $quotaImportBulkTmpFiles;

    $filename = 'temp_quota_bulk_import_'.uniqid('', true).'.xlsx';

    ExcelFacade::store(
        new class($processNumbers) implements FromCollection
        {
            /** @param  list<string>  $processNumbers */
            public function __construct(private readonly array $processNumbers) {}

            public function collection(): \Illuminate\Support\Collection
            {
                return collect(array_map(
                    static fn (string $number): \Illuminate\Support\Collection => collect([$number]),
                    $this->processNumbers,
                ));
            }
        },
        $filename,
        null,
        Excel::XLSX,
    );

    $quotaImportBulkTmpFiles[] = $filename;

    $fullPath = Storage::disk('local')->path($filename);

    if (! file_exists($fullPath)) {
        test()->markTestSkipped('Excel store did not create file at '.$fullPath);
    }

    return new UploadedFile(
        $fullPath,
        'quota-bulk-import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

/**
 * @param  list<array{process_number: string, court?: string, plaintiff?: string, defendant?: string, action?: string}>  $rows
 */
function makePrivateImportExcelUpload(array $rows): UploadedFile
{
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

    foreach ($headers as $index => $title) {
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'1', $title);
    }

    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 2;
        $values = [
            $row['court'] ?? 'JUZGADO TEST QUOTA',
            $row['process_number'],
            'Ejecutivo Singular',
            $row['plaintiff'] ?? 'Demandante Test',
            $row['defendant'] ?? 'Demandado Test',
            $row['action'] ?? 'Auto de prueba',
            '',
            '2026-04-30',
            '2026-04-30',
            '2026-04-24',
        ];

        foreach ($values as $colIndex => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colIndex + 1).$excelRow, $value);
        }
    }

    $tmpPath = tempnam(sys_get_temp_dir(), 'private-quota-import').'.xlsx';
    (new Xlsx($spreadsheet))->save($tmpPath);
    test()->privateImportSpreadsheetTmp = $tmpPath;

    return new UploadedFile(
        $tmpPath,
        'quota-private-import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true,
    );
}

it('enqueues only radicados that fit remaining quota on bulk import', function (): void {
    Bus::fake();

    attachActiveRadicadoForQuotaImportTest($this->organization, [
        '76001333301320170000100',
    ]);

    $file = makeBulkImportExcelUpload([
        '76001333301320170000200',
        '76001333301320170000300',
        '76001333301320170000400',
    ]);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
        ]);

    $response->assertStatus(202)
        ->assertJsonPath('skipped_quota_limit', 2);

    $batchId = $response->json('batch_id');
    expect($batchId)->toBeString()->not->toBe('');

    /** @var ProcessImportBatch $batch */
    $batch = ProcessImportBatch::query()->findOrFail($batchId);

    expect($batch->status)->toBe(ProcessImportBatch::STATUS_PROCESSING)
        ->and($batch->total_count)->toBe(3)
        ->and($batch->failed_count)->toBe(2)
        ->and($batch->enqueued_process_numbers)->toBe(['76001333301320170000200'])
        ->and($batch->errors)->toHaveCount(2)
        ->and($batch->errors[0]['process_number'])->toBe('76001333301320170000300')
        ->and($batch->errors[0]['reason'])->toContain('límite')
        ->and($batch->errors[1]['process_number'])->toBe('76001333301320170000400');

    Bus::assertBatched(static fn ($batch) => $batch->jobs->count() === 1);
});

it('creates failed import batch with per-radicado quota errors when org is at limit', function (): void {
    Bus::fake();
    Notification::fake();

    config(['process-import.admin_report_email' => 'import-report@example.com']);

    attachActiveRadicadoForQuotaImportTest($this->organization, [
        '76001333301320170001100',
        '76001333301320170001200',
    ]);

    $file = makeBulkImportExcelUpload([
        '76001333301320170001300',
        '76001333301320170001400',
    ]);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('skipped_quota_limit', 2)
        ->assertJsonPath('message', fn ($message) => str_contains((string) $message, 'límite'));

    $batchId = $response->json('import_batch_id');
    expect($batchId)->toBeString()->not->toBe('');

    /** @var ProcessImportBatch $batch */
    $batch = ProcessImportBatch::query()->findOrFail($batchId);

    expect($batch->status)->toBe(ProcessImportBatch::STATUS_FAILED)
        ->and($batch->total_count)->toBe(2)
        ->and($batch->failed_count)->toBe(2)
        ->and($batch->success_count)->toBe(0)
        ->and($batch->enqueued_process_numbers)->toBe([])
        ->and($batch->errors)->toHaveCount(2)
        ->and($batch->errors[0])->toMatchArray([
            'process_number' => '76001333301320170001300',
        ])
        ->and($batch->errors[0]['reason'])->toContain('límite');

    Bus::assertNothingBatched();

    Notification::assertSentOnDemand(ProcessImportReportNotification::class);
});

it('exposes quota import errors in admin import history', function (): void {
    Bus::fake();
    Notification::fake();

    config(['process-import.admin_report_email' => 'import-report@example.com']);

    attachActiveRadicadoForQuotaImportTest($this->organization, [
        '76001333301320170002100',
        '76001333301320170002200',
    ]);

    $file = makeBulkImportExcelUpload(['76001333301320170002300']);

    $importResponse = $this->actingAs($this->user)
        ->post('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
        ]);

    $importResponse->assertStatus(422);
    $batchId = $importResponse->json('import_batch_id');

    $historyResponse = $this->actingAs($this->user)
        ->getJson('/api/admin/processes/import-history?has_errors=1');

    $historyResponse->assertOk();

    $batchRow = collect($historyResponse->json('data'))
        ->firstWhere('id', $batchId);

    expect($batchRow)->not->toBeNull()
        ->and($batchRow['failed_count'])->toBe(1)
        ->and($batchRow['errors'])->toHaveCount(1)
        ->and($batchRow['errors'][0]['process_number'])->toBe('76001333301320170002300')
        ->and($batchRow['errors'][0]['reason'])->toContain('límite');
});

it('creates failed private import batch when every new radicado exceeds quota during upload', function (): void {
    Notification::fake();

    config(['process-import.admin_report_email' => 'import-report@example.com']);

    attachActiveRadicadoForQuotaImportTest($this->organization, [
        '76001333301320170003100',
        '76001333301320170003200',
    ]);

    $file = makePrivateImportExcelUpload([
        [
            'process_number' => '76001333301320170003300',
            'plaintiff' => 'Demandante Nuevo A',
        ],
        [
            'process_number' => '76001333301320170003400',
            'plaintiff' => 'Demandante Nuevo B',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('skipped_quota_limit', 2)
        ->assertJsonPath('message', fn ($message) => str_contains((string) $message, 'límite'));

    $batchId = $response->json('import_batch_id');
    /** @var ProcessImportBatch $batch */
    $batch = ProcessImportBatch::query()->findOrFail($batchId);

    expect($batch->is_private_import)->toBeTrue()
        ->and($batch->status)->toBe(ProcessImportBatch::STATUS_FAILED)
        ->and($batch->failed_count)->toBe(2)
        ->and($batch->errors)->toHaveCount(2)
        ->and($batch->errors[0]['process_number'])->toBe('76001333301320170003300')
        ->and($batch->errors[0]['reason'])->toContain('límite')
        ->and($batch->errors[1]['process_number'])->toBe('76001333301320170003400');

    expect(
        Process::query()->whereIn('process_number', [
            '76001333301320170003300',
            '76001333301320170003400',
        ])->count()
    )->toBe(0);

    Notification::assertSentOnDemand(ProcessImportReportNotification::class);
});

it('creates failed private import batch when a single new radicado exceeds quota', function (): void {
    Notification::fake();

    config(['process-import.admin_report_email' => 'import-report@example.com']);

    attachActiveRadicadoForQuotaImportTest($this->organization, [
        '76001333301320170004100',
        '76001333301320170004200',
    ]);

    $file = makePrivateImportExcelUpload([
        [
            'process_number' => '76001333301320170004300',
            'plaintiff' => 'Unico Demandante Bloqueado',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ]);

    $response->assertStatus(422)
        ->assertJsonPath('skipped_quota_limit', 1)
        ->assertJsonPath('message', fn ($message) => str_contains((string) $message, 'límite'));

    $batchId = $response->json('import_batch_id');
    /** @var ProcessImportBatch $batch */
    $batch = ProcessImportBatch::query()->findOrFail($batchId);

    expect($batch->status)->toBe(ProcessImportBatch::STATUS_FAILED)
        ->and($batch->failed_count)->toBe(1)
        ->and($batch->success_count)->toBe(0)
        ->and($batch->errors)->toHaveCount(1)
        ->and($batch->errors[0]['process_number'])->toBe('76001333301320170004300')
        ->and($batch->errors[0]['reason'])->toContain('límite');

    Notification::assertSentOnDemand(ProcessImportReportNotification::class);
});

it('allows partial private import when only some radicados exceed quota', function (): void {
    Queue::fake();

    attachActiveRadicadoForQuotaImportTest($this->organization, [
        '76001333301320170005100',
    ]);

    $file = makePrivateImportExcelUpload([
        [
            'process_number' => '76001333301320170005200',
            'plaintiff' => 'Demandante Permitido',
        ],
        [
            'process_number' => '76001333301320170005300',
            'plaintiff' => 'Demandante Rechazado',
        ],
    ]);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/private-import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
            'data_source_slug' => ProcessDataSourceSlug::PublicacionesProcesales->value,
        ]);

    $response->assertStatus(200)
        ->assertJsonPath('processes_created', 1)
        ->assertJsonPath('skipped_quota_limit', 1);

    $batchId = $response->json('import_batch_id');
    /** @var ProcessImportBatch $batch */
    $batch = ProcessImportBatch::query()->findOrFail($batchId);

    expect($batch->status)->toBe(ProcessImportBatch::STATUS_COMPLETED)
        ->and($batch->errors)->toHaveCount(1)
        ->and($batch->errors[0]['process_number'])->toBe('76001333301320170005300')
        ->and($batch->errors[0]['reason'])->toContain('límite');

    expect(Process::query()->where('process_number', '76001333301320170005200')->exists())->toBeTrue()
        ->and(Process::query()->where('process_number', '76001333301320170005300')->exists())->toBeFalse();
});
