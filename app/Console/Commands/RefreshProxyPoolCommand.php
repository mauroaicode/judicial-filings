<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Application\Shared\Services\ProxyPoolService;

class RefreshProxyPoolCommand extends Command
{
    protected $signature = 'proxy:refresh
                            {--status : Show current pool status without refreshing}';

    protected $description = 'Refresh the proxy pool from Webshare and reset the round-robin pointer';

    public function __construct(
        private readonly ProxyPoolService $proxyPool,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if ($this->option('status')) {
            return $this->showStatus();
        }

        if (! config('judicial-branch.proxy.enabled', false)) {
            $this->warn('Proxy is disabled (JUDICIAL_BRANCH_PROXY_ENABLED=false). Enable it first.');

            return self::FAILURE;
        }

        $this->info('Fetching proxy list from Webshare...');

        $before = $this->proxyPool->count();

        $this->proxyPool->refresh();

        $after = $this->proxyPool->count();

        if ($after === 0) {
            $this->error('Refresh failed: pool is still empty. Check JUDICIAL_BRANCH_PROXY_WEBSHARE_API_KEY and logs.');

            return self::FAILURE;
        }

        $this->info('Pool refreshed successfully.');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Proxies before', $before],
                ['Proxies after (active)', $after],
                ['Provider', config('judicial-branch.proxy.provider', 'webshare')],
            ]
        );

        return self::SUCCESS;
    }

    private function showStatus(): int
    {
        $state = DB::table('proxy_pool_state')->where('id', 1)->first();

        if ($state === null) {
            $this->warn('Pool has never been initialized. Run: php artisan proxy:refresh');

            return self::SUCCESS;
        }

        $ratio = $state->total_count > 0
            ? round($state->active_count / $state->total_count * 100, 1)
            : 0;

        $this->table(
            ['Metric', 'Value'],
            [
                ['Provider', $state->provider],
                ['Total proxies', $state->total_count],
                ['Active proxies', $state->active_count],
                ['Active ratio', "{$ratio}%"],
                ['Current position', $state->current_position],
                ['Last fetched at', $state->last_fetched_at ?? 'Never'],
                ['Proxy enabled', config('judicial-branch.proxy.enabled') ? 'Yes' : 'No'],
            ]
        );

        return self::SUCCESS;
    }
}
