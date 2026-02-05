<?php

declare(strict_types=1);

use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

beforeEach(function (): void {
    $this->organization = Organization::factory()->create();
});

it('filters notifications by organization', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $result = OrganizationNotification::query()
        ->whereOrganization($this->organization->id)
        ->first();

    expect($result)->not->toBeNull();
    expect($result->organization_id)->toBe($this->organization->id);
});

it('filters notifications by notification type', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $result = OrganizationNotification::query()
        ->whereOrganization($this->organization->id)
        ->whereNotificationType('actuacion')
        ->first();

    expect($result)->not->toBeNull();
    expect($result->notification_type)->toBe('actuacion');
});

it('filters unviewed notifications', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action->id,
        'notifiable_type' => $action->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $result = OrganizationNotification::query()
        ->whereOrganization($this->organization->id)
        ->whereUnviewed()
        ->first();

    expect($result)->not->toBeNull();
    expect($result->is_viewed)->toBeFalse();
});

it('orders notifications by created at', function (): void {
    $process = Process::factory()->create();
    $process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
    ]);
    $action1 = ProcessAction::factory()->create(['process_id' => $process->id]);
    $action2 = ProcessAction::factory()->create(['process_id' => $process->id]);

    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action1->id,
        'notifiable_type' => $action1->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);
    OrganizationNotification::create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'organization_id' => $this->organization->id,
        'notifiable_id' => $action2->id,
        'notifiable_type' => $action2->getMorphClass(),
        'notification_type' => 'actuacion',
        'is_viewed' => false,
    ]);

    $results = OrganizationNotification::query()
        ->whereOrganization($this->organization->id)
        ->orderedByCreatedAt()
        ->get();

    expect($results)->toHaveCount(2);
    expect($results->first()->created_at->gte($results->last()->created_at))->toBeTrue();
});
