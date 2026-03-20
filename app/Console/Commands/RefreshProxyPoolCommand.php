<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RefreshProxyPoolCommand extends Command
{
    protected $signature = 'proxy:check
                            {--radicado=76001333302020190011700 : Radicado de prueba para validar la conexión}';

    protected $description = 'Verifica que el proxy residencial rotativo de Webshare funciona contra el Portal Judicial';

    public function handle(): int
    {
        if (! config('judicial-branch.proxy.enabled', false)) {
            $this->warn('Proxy deshabilitado (JUDICIAL_BRANCH_PROXY_ENABLED=false).');

            return self::FAILURE;
        }

        $host     = config('judicial-branch.proxy.host');
        $port     = config('judicial-branch.proxy.port');
        $username = config('judicial-branch.proxy.username');
        $password = config('judicial-branch.proxy.password');

        if (! $username || ! $password) {
            $this->error('Credenciales de proxy no configuradas (JUDICIAL_BRANCH_PROXY_USERNAME / PASSWORD).');

            return self::FAILURE;
        }

        $proxyUrl = "http://{$username}:***@{$host}:{$port}";
        $this->info("Proxy configurado: {$proxyUrl}");
        $this->newLine();

        // Step 1: verify exit IP via Webshare
        $this->line('1. Verificando IP de salida...');
        $ipResponse = Http::timeout(15)
            ->withOptions(['proxy' => "http://{$username}:{$password}@{$host}:{$port}"])
            ->get('https://ipv4.webshare.io/');

        if ($ipResponse->successful()) {
            $this->info("   IP de salida: {$ipResponse->body()}");
        } else {
            $this->error("   Error al obtener IP: HTTP {$ipResponse->status()}");

            return self::FAILURE;
        }

        // Step 2: test against Portal Judicial
        $radicado = (string) $this->option('radicado');
        $apiUrl   = rtrim((string) config('judicial-branch.api_url'), '/');
        $testUrl  = "{$apiUrl}/Procesos/Consulta/NumeroRadicacion?numero={$radicado}&SoloActivos=false&pagina=1";

        $this->line("2. Probando contra el Portal Judicial (radicado: {$radicado})...");

        $ramaResponse = Http::timeout(20)
            ->withHeaders([
                'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Language' => 'es-CO,es;q=0.9',
            ])
            ->withOptions(['proxy' => "http://{$username}:{$password}@{$host}:{$port}"])
            ->get($testUrl);

        $status = $ramaResponse->status();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Proxy',           $proxyUrl],
                ['HTTP Status',     $status],
                ['Resultado',       $status === 200 ? '✓ OK — proxy funciona' : "✗ Bloqueado ({$status})"],
            ]
        );

        if ($status === 200) {
            $this->info('Proxy residencial operativo. Listo para importar radicados.');

            return self::SUCCESS;
        }

        $this->error("Proxy devolvió HTTP {$status}. Verifica las credenciales o el plan en Webshare.");

        return self::FAILURE;
    }
}
