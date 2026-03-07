<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ValidateProxyPoolCommand extends Command
{
    protected $signature = 'proxy:validate-rama
                            {--concurrency=10 : Proxies a probar en paralelo (máx recomendado: 20)}
                            {--timeout=12 : Segundos de timeout por proxy}
                            {--radicado=76001333302020190011700 : Radicado de prueba para validar}';

    protected $description = 'Prueba cada proxy del pool contra Rama Judicial y desactiva los que devuelven 403';

    public function handle(): int
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            $this->warn('Proxy deshabilitado (JUDICIAL_BRANCH_PROXY_ENABLED=false).');

            return self::FAILURE;
        }

        $apiUrl = rtrim((string) config('judicial-branch.api_url'), '/');
        $radicado = (string) $this->option('radicado');
        $concurrency = max(1, min(20, (int) $this->option('concurrency')));
        $timeout = max(5, (int) $this->option('timeout'));

        $testUrl = "{$apiUrl}/Procesos/Consulta/NumeroRadicacion?numero={$radicado}&SoloActivos=false&pagina=1";

        $proxies = DB::table('proxy_pool_entries')
            ->where('is_active', true)
            ->orderBy('position')
            ->get(['id', 'proxy_address']);

        $total = $proxies->count();

        if ($total === 0) {
            $this->warn('Pool vacío. Ejecuta primero: php artisan proxy:refresh');

            return self::FAILURE;
        }

        $this->info("Validando {$total} proxies contra Rama Judicial (concurrencia: {$concurrency}, timeout: {$timeout}s)...");
        $this->newLine();

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0;
        $failed = 0;
        $failedIds = [];

        // Process in chunks to simulate concurrency with curl_multi
        foreach ($proxies->chunk($concurrency) as $chunk) {
            $multiHandle = curl_multi_init();
            $handles = [];

            foreach ($chunk as $entry) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $testUrl,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $timeout,
                    CURLOPT_PROXY => 'http://'.$entry->proxy_address,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_SSL_VERIFYPEER => false,
                ]);
                curl_multi_add_handle($multiHandle, $ch);
                $handles[(int) $ch] = ['handle' => $ch, 'id' => $entry->id, 'proxy' => $entry->proxy_address];
            }

            // Execute all handles in parallel
            do {
                $status = curl_multi_exec($multiHandle, $running);
                if ($running) {
                    curl_multi_select($multiHandle, 1.0);
                }
            } while ($running > 0 && $status === CURLM_OK);

            // Collect results
            foreach ($handles as $info) {
                $ch = $info['handle'];
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_errno($ch);

                if ($httpCode === 200) {
                    $ok++;
                } else {
                    $failed++;
                    $failedIds[] = $info['id'];
                }

                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
                $bar->advance();
            }

            curl_multi_close($multiHandle);
        }

        $bar->finish();
        $this->newLine(2);

        // Bulk-deactivate failed proxies
        if (! empty($failedIds)) {
            DB::table('proxy_pool_entries')
                ->whereIn('id', $failedIds)
                ->update(['is_active' => false, 'updated_at' => now()]);

            DB::table('proxy_pool_state')
                ->where('id', 1)
                ->update(['active_count' => $ok, 'updated_at' => now()]);
        }

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total probados', $total],
                ['Exitosos (HTTP 200)', $ok],
                ['Bloqueados (403/error)', $failed],
                ['% usable', round($ok / $total * 100, 1).'%'],
            ]
        );

        if ($ok === 0) {
            $this->error('Ningún proxy pasó la validación. Considera cambiar el plan o tipo de proxies en Webshare.');

            return self::FAILURE;
        }

        $this->info("Pool listo: {$ok} proxies activos para importación.");

        return self::SUCCESS;
    }
}
