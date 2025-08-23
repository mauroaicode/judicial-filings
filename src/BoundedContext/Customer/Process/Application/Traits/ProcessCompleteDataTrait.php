<?php

declare(strict_types=1);

namespace Core\BoundedContext\Customer\Process\Application\Traits;

use Core\Shared\Infrastructure\Services\JudicialBranchConsultService;
use Illuminate\Support\Facades\Log;

trait ProcessCompleteDataTrait
{
    /**
     * Unifica los datos completos del proceso (con llamada a API para obtener detalle)
     */
    private function getCompleteProcessData(array $processBasic, string $filingNumber, JudicialBranchConsultService $judicialService): ?array
    {
        try {
            $responseProcessDetail = $judicialService->fetchDetailProcess($processBasic['idProceso']);

            if (!$responseProcessDetail->isSuccessful) {
                Log::channel('judicial_process_chunk_job')->warning("No se pudo obtener detalle para proceso: {$processBasic['idProceso']}");
                return null;
            }

            $processDetail = $responseProcessDetail->data;

            return [
                'process_id' => $processBasic['idProceso'],
                'process_number' => $filingNumber,
                'fechaProceso' => $processBasic['fechaProceso'],
                'fechaUltimaActuacion' => $processBasic['fechaUltimaActuacion'],
                'despacho' => $processBasic['despacho'],
                'departamento' => $processBasic['departamento'],
                'sujetosProcesales' => $processBasic['sujetosProcesales'],
                'esPrivado' => $processBasic['esPrivado'],

                // Datos del detalle del proceso
                'tipoProceso' => $processDetail['tipoProceso'] ?? 'N/A',
                'claseProceso' => $processDetail['claseProceso'] ?? 'N/A',
                'subclaseProceso' => $processDetail['subclaseProceso'] ?? null,
                'ubicacion' => $processDetail['ubicacion'] ?? null,
                'contenidoRadicacion' => $processDetail['contenidoRadicacion'] ?? null,
                'ponente' => $processDetail['ponente'] ?? null,
                'codDespachoCompleto' => $processDetail['codDespachoCompleto'] ?? null,

                'last_api_update' => now(),
            ];

        } catch (\Exception $e) {
            Log::channel('judicial_process_chunk_job')->error("Error unificando datos del proceso {$processBasic['idProceso']}: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'process_id' => $processBasic['idProceso'],
                'filing_number' => $filingNumber,
            ]);
            return null;
        }
    }
}
