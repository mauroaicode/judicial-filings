<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\ProcessImportBatch;
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

    $adminRole = Role::query()->firstOrCreate(['name' => 'admin', 'guard_name' => 'admin']);
    $this->user->roles()->attach($adminRole->id);

    $this->organization = Organization::factory()->create();
});

it('requires authentication to import processes', function (): void {
    $file = UploadedFile::fake()->create('import.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->postJson('/api/admin/processes/import', [
        'organization_id' => $this->organization->id,
        'file' => $file,
    ]);

    $response->assertStatus(401);
});

it('validates that organization_id is required', function (): void {
    $file = UploadedFile::fake()->create('import.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', [
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['organization_id']);
});

it('validates that file is required', function (): void {
    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('validates file mime type', function (): void {
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['file']);
});

it('validates organization exists', function (): void {
    $file = UploadedFile::fake()->create('import.xlsx', 1024, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $response = $this->actingAs($this->user)
        ->postJson('/api/admin/processes/import', [
            'organization_id' => '00000000-0000-0000-0000-000000000000',
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['organization_id']);
});

it('returns 422 when excel has no valid radicado numbers', function (): void {
    Bus::fake();

    $batchCountBefore = ProcessImportBatch::query()->count();

    $filename = 'temp_empty_import.xlsx';
    \Maatwebsite\Excel\Facades\Excel::store(
        new class implements \Maatwebsite\Excel\Concerns\FromCollection
        {
            public function collection(): \Illuminate\Support\Collection
            {
                return collect([collect(['solo texto'])]);
            }
        },
        $filename,
        null,
        \Maatwebsite\Excel\Excel::XLSX
    );
    $fullPath = Storage::disk('local')->path($filename);
    if (! file_exists($fullPath)) {
        test()->markTestSkipped('Excel store did not create file at '.$fullPath);
    }
    $file = new UploadedFile($fullPath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
        ]);

    $response->assertStatus(422);
    expect(ProcessImportBatch::query()->count())->toBe($batchCountBefore);
    Storage::disk('local')->delete($filename);
});

it('returns 422 with row errors when excel has invalid digit count', function (): void {
    Bus::fake();

    $filename = 'temp_invalid_import.xlsx';
    \Maatwebsite\Excel\Facades\Excel::store(
        new class implements \Maatwebsite\Excel\Concerns\FromCollection
        {
            public function collection(): \Illuminate\Support\Collection
            {
                return collect([collect(['123'])]);
            }
        },
        $filename,
        null,
        \Maatwebsite\Excel\Excel::XLSX
    );
    $fullPath = Storage::disk('local')->path($filename);
    if (! file_exists($fullPath)) {
        test()->markTestSkipped('Excel store did not create file at '.$fullPath);
    }
    $file = new UploadedFile($fullPath, $filename, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

    $response = $this->actingAs($this->user)
        ->post('/api/admin/processes/import', [
            'organization_id' => $this->organization->id,
            'file' => $file,
        ]);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.rows.1', fn ($v) => str_contains((string) $v, '23'));
    Storage::disk('local')->delete($filename);
});
