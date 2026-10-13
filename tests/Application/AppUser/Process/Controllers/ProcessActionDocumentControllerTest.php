<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\AppUser\Models\AppUser;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

function historyFakePdf(string $name = 'auto.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"
    );
}

beforeEach(function (): void {
    Storage::fake('local');
    config(['media-library.disk_name' => 'local']);
    Queue::fake();

    $this->admin = User::factory()->create([
        'email' => 'admin-doc-history@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);
    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->admin->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create([
        'is_active' => true,
        'can_view_action_documents' => true,
    ]);
    $this->otherOrganization = Organization::factory()->create([
        'is_active' => true,
        'can_view_action_documents' => true,
    ]);

    $this->appUser = AppUser::factory()->create([
        'email' => 'app-doc-history@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
    ]);
    $this->appUser->organizations()->attach($this->organization->id, ['is_owner' => true]);

    $this->process = Process::factory()->create();
    $this->process->organizations()->attach($this->organization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);
    $this->process->organizations()->attach($this->otherOrganization->id, [
        'interest_date' => now()->toDateString(),
        'is_active' => true,
        'status' => 'active',
    ]);

    $this->action = ProcessAction::factory()->create([
        'process_id' => $this->process->id,
        'action' => 'Auto interlocutorio',
        'action_date' => '2026-10-09',
    ]);
});

it('lists process action documents newest first for the app user org', function (): void {
    $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [historyFakePdf('older.pdf')],
            'organization_ids' => [$this->organization->id],
        ])
        ->assertCreated();

    $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [historyFakePdf('newer.pdf')],
            'organization_ids' => [$this->organization->id],
        ])
        ->assertCreated();

    $response = $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$this->process->id}/action-documents?per_page=12");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                [
                    'id',
                    'original_filename',
                    'size_bytes',
                    'mime_type',
                    'created_at',
                    'action_id',
                    'action',
                    'action_date',
                ],
            ],
            'current_page',
            'per_page',
            'total',
            'last_page',
        ])
        ->assertJsonPath('data.0.original_filename', 'newer.pdf')
        ->assertJsonPath('data.0.action_id', $this->action->id)
        ->assertJsonPath('data.0.action', 'Auto interlocutorio')
        ->assertJsonPath('data.0.action_date', DateFormatHelper::formatDateWithDayOfWeek('2026-10-09'))
        ->assertJsonPath('total', 2);

    expect($response->json('data.0.created_at'))->toContain(',');
});

it('shows documents uploaded for another interested org when viewing is enabled', function (): void {
    $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [historyFakePdf('only-other.pdf')],
            'organization_ids' => [$this->otherOrganization->id],
        ])
        ->assertCreated();

    $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$this->process->id}/action-documents")
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.original_filename', 'only-other.pdf');
});

it('returns empty list when org cannot view action documents', function (): void {
    $this->actingAs($this->admin)
        ->post("/api/admin/processes/{$this->process->id}/actions/{$this->action->id}/documents", [
            'files' => [historyFakePdf()],
            'organization_ids' => [$this->organization->id],
        ])
        ->assertCreated();

    $this->organization->update(['can_view_action_documents' => false]);

    $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$this->process->id}/action-documents")
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('data', []);
});

it('returns 404 when process is not linked to the user organization', function (): void {
    $foreign = Process::factory()->create();

    $this->actingAs($this->appUser)
        ->getJson("/api/app-user/processes/{$foreign->id}/action-documents")
        ->assertNotFound();
});
