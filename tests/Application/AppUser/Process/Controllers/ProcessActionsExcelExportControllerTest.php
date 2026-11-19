<?php

declare(strict_types=1);

use Maatwebsite\Excel\Facades\Excel;
use Src\Application\AppUser\Process\Excel\CollectProcessActionsExcelRowsService;
use Src\Application\Shared\Process\Data\ProcessActionFilterData;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->process = Process::factory()->create([
        'process_number' => '76001418900720220047300',
        'court' => 'Juzgado 007 de Pequeñas Causas',
    ]);
    $this->process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $this->insideRange = ProcessAction::factory()->create([
        'process_id' => $this->process->id,
        'action' => 'Auto Decide',
        'action_date' => '2026-11-19',
        'registration_date' => '2026-11-18',
        'cons_action' => 10,
    ]);
    $this->outsideRange = ProcessAction::factory()->create([
        'process_id' => $this->process->id,
        'action' => 'Traslado Secretarial',
        'action_date' => '2025-10-10',
        'registration_date' => '2025-10-09',
        'cons_action' => 2,
    ]);
});

it('requires authentication to export process actions excel', function (): void {
    get("/api/app-user/processes/{$this->process->id}/actions/export")
        ->assertUnauthorized();
});

it('returns 404 when exporting actions of a process outside the organization', function (): void {
    $foreign = Process::factory()->create();

    actingAs($this->appUser, 'sanctum')
        ->get("/api/app-user/processes/{$foreign->id}/actions/export")
        ->assertNotFound();
});

it('downloads an excel with actuaciones of the process', function (): void {
    Excel::fake();

    $response = actingAs($this->appUser, 'sanctum')
        ->get("/api/app-user/processes/{$this->process->id}/actions/export");

    $response->assertOk();
    Excel::assertDownloaded('actuaciones-76-001-41-89-007-2022-00473-00.xlsx');
});

it('applies action date filters to the excel rows', function (): void {
    $rows = iterator_to_array(
        app(CollectProcessActionsExcelRowsService::class)->yieldRows(
            $this->process,
            ProcessActionFilterData::from([
                'action_date_from' => '2026-11-01',
                'action_date_to' => '2026-11-30',
            ]),
        )
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Actuación'])->toBe('Auto Decide')
        ->and($rows[0]['Número de radicado'])->toBe('76-001-41-89-007-2022-00473-00');
});

it('applies registration date filters to the excel rows', function (): void {
    $rows = iterator_to_array(
        app(CollectProcessActionsExcelRowsService::class)->yieldRows(
            $this->process,
            ProcessActionFilterData::from([
                'registration_date_from' => '2025-01-01',
                'registration_date_to' => '2025-12-31',
            ]),
        )
    );

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['Actuación'])->toBe('Traslado Secretarial');
});
