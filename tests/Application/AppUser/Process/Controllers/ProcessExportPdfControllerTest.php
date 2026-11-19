<?php

declare(strict_types=1);

use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Enums\ProcessLawyerRole;
use Src\Domain\Process\Enums\ProcessTimelineEventSource;
use Src\Domain\Process\Enums\ProcessTimelineEventType;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;
use Src\Domain\Process\Models\ProcessTimelineEvent;
use Src\Domain\Task\Enums\TaskStatus;
use Src\Domain\Task\Enums\TaskType;
use Src\Domain\Task\Models\Task;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create(['name' => 'Bufete PDF Proceso']);
    $this->appUser = AppUser::factory()->create();
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->process = Process::factory()->create([
        'process_number' => '76001418900720220047300',
        'court' => 'Juzgado 007 de Pequeñas Causas y Competencia Múltiple de Cali',
        'has_multiple_instances' => true,
        'speaker' => 'Tito Andres Perez Otavo',
    ]);
    $this->process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'pending_hearing',
        'lawyer_role' => ProcessLawyerRole::PLAINTIFF->value,
    ]);

    ProcessSubject::factory()->forProcess($this->process)->create([
        'subject_type' => 'Demandante',
        'name_or_business_name' => 'Fondo Especial de Vivienda',
    ]);
    ProcessSubject::factory()->forProcess($this->process)->create([
        'subject_type' => 'Demandado',
        'name_or_business_name' => 'Segundo Jeremias Cabezas',
    ]);

    ProcessAction::factory()->create([
        'process_id' => $this->process->id,
        'action' => 'Fijacion Estado',
        'annotation' => 'recurso',
        'action_date' => '2026-11-19',
        'registration_date' => '2026-11-18',
    ]);

    Task::factory()->create([
        'process_id' => $this->process->id,
        'organization_id' => $this->organization->id,
        'title' => 'Pruebas exportar pdf',
        'type' => TaskType::PENDING_HEARING,
        'status' => TaskStatus::PENDING,
        'due_date' => now()->addDays(4),
        'reminder_days' => 0,
        'is_admin' => false,
    ]);

    ProcessTimelineEvent::factory()->create([
        'process_id' => $this->process->id,
        'process_number' => $this->process->process_number,
        'organization_id' => $this->organization->id,
        'event_type' => ProcessTimelineEventType::TASK_CREATED,
        'source' => ProcessTimelineEventSource::USER,
        'payload' => ['title' => 'Pruebas exportar pdf'],
    ]);
});

it('requires authentication to export a process pdf', function (): void {
    get("/api/app-user/processes/{$this->process->id}/export-pdf")
        ->assertUnauthorized();
});

it('returns 404 when the process belongs to another organization', function (): void {
    $foreign = Process::factory()->create();

    actingAs($this->appUser, 'sanctum')
        ->get("/api/app-user/processes/{$foreign->id}/export-pdf")
        ->assertNotFound();
});

it('downloads a pdf of the process detail', function (): void {
    $response = actingAs($this->appUser, 'sanctum')
        ->get("/api/app-user/processes/{$this->process->id}/export-pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment')
        ->and($disposition)->toContain('proceso-')
        ->and($disposition)->toContain('.pdf')
        ->and($disposition)->not->toContain($this->process->id)
        ->and($response->getContent())->toStartWith('%PDF');
});
