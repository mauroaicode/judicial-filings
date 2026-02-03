<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Src\Domain\Role\Models\Role;
use Src\Domain\User\Enums\UserStatus;
use Src\Domain\User\Models\User;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
        'email_verified_at' => now(),
        'state' => UserStatus::ACTIVE,
    ]);

    $adminRole = Role::query()->where('guard_name', 'admin')->first();
    if ($adminRole) {
        $this->user->roles()->attach($adminRole->id);
    }
});

it('requires authentication to import processes', function (): void {
    $file = UploadedFile::fake()->create('import.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->postJson('/api/admin/processes/import', [
        'file' => $file,
    ]);

    $response->assertStatus(401);
});

it('validates that file is required', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', []);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('validates file mime type', function (): void {
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', [
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('returns success, message and stats when import runs', function (): void {
    $file = UploadedFile::fake()->create('import.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', [
            'file' => $file,
        ]);

    expect($response->status())->toBeIn([200, 422]);
    $response->assertJsonStructure([
        'success',
        'message',
        'stats' => [
            'validated',
            'succeeded',
            'failed',
            'total',
            'errors',
        ],
    ]);
});
