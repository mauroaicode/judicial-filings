<?php

declare(strict_types=1);

namespace Src\Application\Shared\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Src\Application\Shared\Presence\Services\RecordAppUserDailyActivityService;

class RecordAppUserDailyActivityJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int */
    public $tries = 3;

    /** @var int */
    public $timeout = 30;

    public function __construct(
        public string $appUserId,
        public ?string $ip,
        public bool $incrementLogin,
    ) {
        $this->queue = (string) config('presence.queue', 'presence');

        $connection = config('presence.connection');

        if (is_string($connection) && $connection !== '') {
            $this->connection = $connection;
        }
    }

    public function handle(RecordAppUserDailyActivityService $service): void
    {
        $service->handle($this->appUserId, $this->ip, $this->incrementLogin);
    }
}
