<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Src\Domain\Notification\Models\NotificationDigest;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin-digest-pdf@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create(['name' => 'Bufete Export PDF']);
    $this->otherOrganization = Organization::factory()->create(['name' => 'Otro Bufete']);

    $this->digest = NotificationDigest::factory()->create([
        'organization_id' => $this->organization->id,
        'email_sent_at' => now(),
        'created_at' => now()->setTime(13, 0),
        'data' => [
            [
                'court' => 'Juzgado 002 de Pequeñas Causas y Competencia Múltiple de Cali',
                'process_number' => '76001418900220250039700',
                'demandante' => 'Banco Av Villas, Seguros Del Estado SA',
                'demandado' => 'Alexandra Ortiz Reyes',
                'action_date' => '13/10/2026',
                'registration_date' => '13/10/2026',
                'action_text' => 'Fijación Estado',
                'annotation' => 'Decisión automática',
                'is_alert' => false,
            ],
        ],
    ]);
});

it('requires authentication to export a consolidado pdf', function (): void {
    $this->get("/api/admin/notification-digests/{$this->digest->id}/export-pdf")
        ->assertUnauthorized();
});

it('forbids non-admin users from exporting a consolidado pdf', function (): void {
    $plain = User::factory()->create([
        'email' => 'plain-admin-digest-pdf@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $this->actingAs($plain)
        ->get("/api/admin/notification-digests/{$this->digest->id}/export-pdf")
        ->assertForbidden();
});

it('downloads the same consolidado pdf from the admin digest list', function (): void {
    $response = $this->actingAs($this->user)
        ->get("/api/admin/notification-digests/{$this->digest->id}/export-pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');

    $disposition = (string) $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment')
        ->and($disposition)->toContain('consolidado-')
        ->and($disposition)->toContain('.pdf')
        ->and($disposition)->not->toContain($this->digest->id)
        ->and($response->getContent())->toStartWith('%PDF');
});

it('downloads the consolidado pdf from the organization detail', function (): void {
    $response = $this->actingAs($this->user)
        ->get("/api/admin/organizations/{$this->organization->id}/notification-digests/{$this->digest->id}/export-pdf");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

it('returns 404 when the consolidado does not belong to the organization', function (): void {
    $this->actingAs($this->user)
        ->get("/api/admin/organizations/{$this->otherOrganization->id}/notification-digests/{$this->digest->id}/export-pdf")
        ->assertNotFound();
});
