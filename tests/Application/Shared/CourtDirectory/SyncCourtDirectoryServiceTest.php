<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Queue;
use Src\Application\Shared\CourtDirectory\Jobs\SyncCourtDirectoryJob;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncStatus;
use Src\Domain\CourtDirectory\Enums\CourtDirectorySyncTrigger;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;
use Src\Domain\CourtDirectory\Models\CourtDirectorySyncRun;
use Tests\Support\CourtDirectoryReportFake;

beforeEach(function (): void {
    CourtDirectoryEntry::query()->delete();
    CourtDirectorySyncRun::query()->delete();
});

it('upserts directory rows by email and marks missing rows inactive', function (): void {
    CourtDirectoryReportFake::fake([
        directoryRow('despacho001@cendoj.ramajudicial.gov.co', 'Juzgado 001', '760014189001'),
        directoryRow('despacho002@cendoj.ramajudicial.gov.co', 'Juzgado 002', '760014189001'),
    ]);

    $this->artisan('court-directory:sync')->assertSuccessful();

    expect(CourtDirectoryEntry::query()->count())->toBe(2)
        ->and(CourtDirectoryEntry::query()->where('desk_code', '760014189001')->count())->toBe(2)
        ->and(CourtDirectorySyncRun::query()->first()?->trigger)->toBe(CourtDirectorySyncTrigger::Command);

    CourtDirectoryReportFake::fake([
        directoryRow('despacho001@cendoj.ramajudicial.gov.co', 'Juzgado 001 actualizado', '760014189001'),
    ]);

    $this->artisan('court-directory:sync')->assertSuccessful();

    $kept = CourtDirectoryEntry::query()->where('email', 'despacho001@cendoj.ramajudicial.gov.co')->first();
    $missing = CourtDirectoryEntry::query()->where('email', 'despacho002@cendoj.ramajudicial.gov.co')->first();

    expect(CourtDirectoryEntry::query()->count())->toBe(2)
        ->and($kept?->name)->toBe('Juzgado 001 actualizado')
        ->and($kept?->is_active)->toBeTrue()
        ->and($kept?->missing_at)->toBeNull()
        ->and($missing?->is_active)->toBeFalse()
        ->and($missing?->missing_at)->not->toBeNull()
        ->and(CourtDirectorySyncRun::query()->orderByDesc('finished_at')->first()?->rows_marked_inactive)->toBe(1);
});

it('queues the directory sync on its own horizon queue', function (): void {
    Queue::fake();

    $this->artisan('court-directory:sync')->assertSuccessful();

    Queue::assertPushedOn('court-directory', SyncCourtDirectoryJob::class);
    expect(CourtDirectoryEntry::query()->count())->toBe(0)
        ->and(CourtDirectorySyncRun::query()->first()?->status)->toBe(CourtDirectorySyncStatus::Running);
});

it('keeps the stored directory when the report returns no rows', function (): void {
    CourtDirectoryEntry::factory()->create([
        'email' => 'despacho001@cendoj.ramajudicial.gov.co',
        'is_active' => true,
    ]);

    CourtDirectoryReportFake::fake([]);

    $this->artisan('court-directory:sync')->assertFailed();

    expect(CourtDirectoryEntry::query()->where('is_active', true)->count())->toBe(1)
        ->and(CourtDirectorySyncRun::query()->first()?->status)->toBe(CourtDirectorySyncStatus::Failed);
});

function directoryRow(string $email, string $name, string $deskCode): array
{
    return [
        'email' => $email,
        'name' => $name,
        'corporation' => 'Juzgado Municipal',
        'specialty' => 'Civil',
        'department' => 'Valle',
        'city' => 'Cali',
        'account_type' => 'Área',
        'desk_code' => $deskCode,
    ];
}
