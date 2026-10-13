<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Src\Application\Shared\Jobs\SendOrganizationNotificationJob;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Media\Models\Media;
use Src\Domain\Notification\Models\OrganizationNotification;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

function fakePdf(string $name = 'auto.pdf'): UploadedFile
{
    // Minimal PDF header so mime detection is application/pdf (empty fakes become application/x-empty).
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"
    );
}

beforeEach(function (): void {
    Storage::fake('local');
    config(['media-library.disk_name' => 'local']);

    $this->admin = User::factory()->create([
        'email' => 'admin-action-docs@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->admin->roles()->attach($adminRole->id);

    $this->process = Process::factory()->create();
    $this->action = ProcessAction::factory()->create([
        'process_id' => $this->process->id,
    ]);

    $this->orgA = Organization::factory()->create([
        'is_active' => true,
        'can_view_action_documents' => true,
    ]);
    $this->orgB = Organization::factory()->create([
        'is_active' => true,
        'can_view_action_documents' => true,
    ]);
    $this->orgC = Organization::factory()->create([
        'is_active' => true,
        'can_view_action_documents' => false,
    ]);

    foreach ([$this->orgA, $this->orgB, $this->orgC] as $org) {
        $this->process->organizations()->attach($org->id, [
            'interest_date' => now()->toDateString(),
            'is_active' => true,
            'status' => 'active',
        ]);
    }
});

it('uploads multiple pdfs and grants access only to selected organizations', function (): void {
    Queue::fake();

    $response = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [
                fakePdf('auto.pdf'),
                fakePdf('edicto.pdf'),
            ],
            'organization_ids' => [$this->orgA->id],
        ]);

    $response->assertCreated()
        ->assertJsonPath('documents.0.organization_ids.0', $this->orgA->id)
        ->assertJsonCount(2, 'documents');

    expect(
        Media::query()
            ->where('collection_name', Media::COLLECTION_ACTION_DOCUMENTS)
            ->where('model_id', $this->action->id)
            ->count()
    )->toBe(2);

    $mediaId = (int) $response->json('documents.0.id');
    $this->assertDatabaseHas('media_organization', [
        'media_id' => $mediaId,
        'organization_id' => $this->orgA->id,
    ]);
    $this->assertDatabaseMissing('media_organization', [
        'media_id' => $mediaId,
        'organization_id' => $this->orgB->id,
    ]);

    // Org A (selected) + Org B (already can_view + linked) both get notified.
    Queue::assertPushed(SendOrganizationNotificationJob::class, 2);
});

it('enables can_view_action_documents when an org is selected on upload', function (): void {
    Queue::fake();

    expect($this->orgC->can_view_action_documents)->toBeFalse();

    $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id, $this->orgC->id],
        ])
        ->assertCreated();

    expect($this->orgC->fresh()->can_view_action_documents)->toBeTrue();

    expect(
        OrganizationNotification::query()
            ->where('notification_type', 'actuacion_documento')
            ->where('organization_id', $this->orgC->id)
            ->exists()
    )->toBeTrue();
});

it('does not disable can_view_action_documents when an org is omitted from acl sync', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id, $this->orgB->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    $this->actingAs($this->admin)
        ->putJson("/api/admin/media/{$mediaId}/organizations", [
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertOk();

    expect($this->orgB->fresh()->can_view_action_documents)->toBeTrue();
    $this->assertDatabaseMissing('media_organization', [
        'media_id' => $mediaId,
        'organization_id' => $this->orgB->id,
    ]);
});

it('grants access to another organization without reuploading', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    $this->actingAs($this->admin)
        ->putJson("/api/admin/media/{$mediaId}/organizations", [
            'organization_ids' => [$this->orgA->id, $this->orgC->id],
        ])
        ->assertOk()
        ->assertJsonPath('document.organization_ids', fn ($ids) => collect($ids)->contains($this->orgC->id));

    $this->assertDatabaseHas('media_organization', [
        'media_id' => $mediaId,
        'organization_id' => $this->orgC->id,
    ]);

    expect(
        Media::query()
            ->where('collection_name', Media::COLLECTION_ACTION_DOCUMENTS)
            ->where('model_id', $this->action->id)
            ->count()
    )->toBe(1);
});

it('rejects organization ids not linked to the process', function (): void {
    $outsider = Organization::factory()->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$outsider->id],
        ])
        ->assertStatus(422);
});

it('lets app user download when org has flag and acl access', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    $appUser = AppUser::factory()->create();
    $appUser->organizations()->attach($this->orgA->id, ['is_owner' => true]);

    $this->actingAs($appUser)
        ->get("/api/app-user/media/{$mediaId}/download")
        ->assertOk();
});

it('hides documents from app user when org cannot view action documents', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    // Kill-switch via settings (upload itself enables the flag for selected orgs).
    $this->orgA->update(['can_view_action_documents' => false]);

    $appUser = AppUser::factory()->create();
    $appUser->organizations()->attach($this->orgA->id, ['is_owner' => true]);

    $this->actingAs($appUser)
        ->get("/api/app-user/media/{$mediaId}/download")
        ->assertNotFound();

    $this->actingAs($appUser)
        ->getJson("/api/app-user/processes/{$this->process->id}/actions")
        ->assertOk()
        ->assertJsonPath('data.0.documents', []);
});

it('lets interested org with document viewing enabled download even if not on upload acl', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    // Org B already has can_view_action_documents and is linked to the process.
    $appUserB = AppUser::factory()->create();
    $appUserB->organizations()->attach($this->orgB->id, ['is_owner' => true]);

    $this->actingAs($appUserB)
        ->get("/api/app-user/media/{$mediaId}/download")
        ->assertOk();
});

it('lets admin download (view) a document via authenticated endpoint', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    $this->actingAs($this->admin)
        ->get("/api/admin/media/{$mediaId}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('returns 404 when admin downloads a missing document', function (): void {
    $this->actingAs($this->admin)
        ->get('/api/admin/media/999999/download')
        ->assertNotFound();
});

it('deletes a document', function (): void {
    Queue::fake();

    $upload = $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [fakePdf()],
            'organization_ids' => [$this->orgA->id],
        ])
        ->assertCreated();

    $mediaId = (int) $upload->json('documents.0.id');

    $this->actingAs($this->admin)
        ->deleteJson("/api/admin/media/{$mediaId}")
        ->assertOk();

    expect(Media::query()->whereKey($mediaId)->exists())->toBeFalse();
});

it('updates can_view_action_documents from organization settings', function (): void {
    $this->actingAs($this->admin)
        ->putJson("/api/admin/organizations/{$this->orgB->id}/settings", [
            'can_view_action_documents' => true,
        ])
        ->assertOk()
        ->assertJsonPath('settings.can_view_action_documents', true);

    expect($this->orgB->fresh()->can_view_action_documents)->toBeTrue();
});
