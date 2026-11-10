<?php

declare(strict_types=1);

use Src\Application\Shared\Services\Process\JudicialSyncLiveStatusBroadcaster;
use Src\Domain\JudicialSync\Enums\JudicialSyncRunStatus;
use Src\Domain\JudicialSync\Models\JudicialSyncRun;

it('keeps the sync running when reverb is unreachable', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1,
        'broadcasting.connections.reverb.options.scheme' => 'http',
        'broadcasting.connections.reverb.options.useTLS' => false,
    ]);

    app()->forgetInstance('Illuminate\Broadcasting\BroadcastManager');

    $run = JudicialSyncRun::factory()->create([
        'status' => JudicialSyncRunStatus::Started,
        'processes_queued' => 0,
    ]);

    app(JudicialSyncLiveStatusBroadcaster::class)->broadcast($run, force: true);

    expect($run->fresh()->status)->toBe(JudicialSyncRunStatus::Started);
});
