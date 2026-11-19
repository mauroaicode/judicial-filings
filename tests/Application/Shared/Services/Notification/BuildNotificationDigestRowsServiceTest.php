<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Src\Application\Shared\Services\Notification\BuildNotificationDigestRowsService;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

it('emits one consolidado row per auto when one fijacion covers two same-day autos', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create(['process_number' => '76001400302820260129100']);
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $regDate = now()->startOfDay();
    $morphClass = (new ProcessAction)->getMorphClass();

    $fijacion = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijación Estado',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 1,
    ]);
    $autoMandamiento = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Libra Mandamiento de Pago (auto Interlocutorio N 3926)',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 2,
    ]);
    $autoMedida = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Decreta Medida Cautelar (auto Interlocutorio N 3927)',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 3,
    ]);

    $notifications = collect([$fijacion, $autoMandamiento, $autoMedida])->map(function (ProcessAction $action) use ($organization, $morphClass): OrganizationNotification {
        return OrganizationNotification::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'notifiable_id' => $action->id,
            'notifiable_type' => $morphClass,
            'notification_type' => 'actuacion',
            'is_viewed' => false,
            'is_notified' => false,
            'is_email_notified' => false,
        ]);
    });

    $rows = app(BuildNotificationDigestRowsService::class)
        ->handle(
            OrganizationNotification::query()->whereIn('id', $notifications->pluck('id'))->with('notifiable.process')->get(),
            $organization->id,
        );

    expect($rows)->toHaveCount(2)
        ->and($rows->every(fn (array $row): bool => ($row['is_merged'] ?? false) === true))->toBeTrue()
        ->and($rows->pluck('linked_action_text')->implode(' | '))->toContain('Mandamiento')
        ->and($rows->pluck('linked_action_text')->implode(' | '))->toContain('Medida Cautelar')
        ->and($rows->pluck('process_action_id')->unique()->count())->toBe(2)
        ->and($rows->contains('process_action_id', $fijacion->id))->toBeTrue();
});

it('keeps a single fijacion+auto consolidado row keyed by the fijacion', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $regDate = now()->startOfDay();
    $morphClass = (new ProcessAction)->getMorphClass();

    $fijacion = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijación Estado',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 10,
    ]);
    $auto = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Niega Control De Legalidad',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 11,
    ]);

    foreach ([$fijacion, $auto] as $action) {
        OrganizationNotification::query()->create([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'notifiable_id' => $action->id,
            'notifiable_type' => $morphClass,
            'notification_type' => 'actuacion',
            'is_viewed' => false,
            'is_notified' => false,
            'is_email_notified' => false,
        ]);
    }

    $rows = app(BuildNotificationDigestRowsService::class)->handle(
        OrganizationNotification::query()->where('organization_id', $organization->id)->with('notifiable.process')->get(),
        $organization->id,
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['is_merged'])->toBeTrue()
        ->and($rows->first()['process_action_id'])->toBe($fijacion->id)
        ->and($rows->first()['notified_action_id'])->toBe($auto->id)
        ->and($rows->first()['linked_action_text'])->toContain('Auto Niega');
});

it('merges a leftover auto with the same-day fijacion even if that fijacion was not in the package', function (): void {
    $organization = Organization::factory()->create();
    $process = Process::factory()->create();
    $process->organizations()->attach($organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);

    $regDate = now()->startOfDay();
    $morphClass = (new ProcessAction)->getMorphClass();

    ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Fijación Estado',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 24,
    ]);
    $auto = ProcessAction::factory()->create([
        'process_id' => $process->id,
        'action' => 'Auto Fija Fecha y Hora de Audiencia para el Dia 21 de Enero de 2026',
        'registration_date' => $regDate,
        'action_date' => $regDate,
        'cons_action' => 25,
    ]);

    OrganizationNotification::query()->create([
        'id' => (string) Str::uuid(),
        'organization_id' => $organization->id,
        'notifiable_id' => $auto->id,
        'notifiable_type' => $morphClass,
        'notification_type' => 'actuacion',
        'is_viewed' => false,
        'is_notified' => false,
        'is_email_notified' => false,
    ]);

    $rows = app(BuildNotificationDigestRowsService::class)->handle(
        OrganizationNotification::query()->where('organization_id', $organization->id)->with('notifiable.process')->get(),
        $organization->id,
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['is_merged'])->toBeTrue()
        ->and($rows->first()['notified_action_id'])->toBe($auto->id)
        ->and($rows->first()['linked_action_text'])->toContain('Auto Fija Fecha');
});
