<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Src\Application\Admin\Process\Data\ProcessImportFromExcelData;
use Src\Application\Admin\Process\DTOs\ProcessImportDataResult;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;

readonly class ProcessImportDataService
{
    /**
     * Valida request, organización activa, parsea Excel y filtra ya registrados.
     * Retorna resultado con status/body o datos listos para encolar.
     */
    public function handle(Request $request): ProcessImportDataResult
    {
        $data = ProcessImportFromExcelData::from($request);

        $organization = Organization::query()->find($data->organization_id);
        if (! $organization || ! $organization->is_active) {
            return new ProcessImportDataResult(422, [
                'message' => __('process.organization_inactive'),
            ]);
        }

        $reader = new ProcessImportExcelReader($data->file);
        $result = $reader->parse();

        if ($result->hasErrors()) {
            return new ProcessImportDataResult(422, [
                'message' => __('process.import_validation_failed'),
                'errors' => ['rows' => $result->rowErrors],
            ]);
        }

        if ($result->validNumbers === []) {
            return new ProcessImportDataResult(422, [
                'message' => __('process.import_validation_failed'),
                'errors' => ['file' => [__('validation.process_number.regex')]],
            ]);
        }

        $alreadyRegistered = Process::query()
            ->whereIn('process_number', $result->validNumbers)
            ->whereHas('organizations', fn ($q) => $q->where('organizations.id', $data->organization_id))
            ->pluck('process_number');

        $toEnqueue = array_values(array_diff($result->validNumbers, $alreadyRegistered->all()));
        $skippedAlreadyRegistered = count($result->validNumbers) - count($toEnqueue);

        $logChannel = config('process-import.log_channel', 'process_import');
        Log::channel($logChannel)->info('Import started: parsing and enqueue', [
            'valid_numbers_from_excel' => count($result->validNumbers),
            'already_registered_for_org' => $alreadyRegistered->count(),
            'to_enqueue' => count($toEnqueue),
            'organization_id' => $data->organization_id,
        ]);

        if ($toEnqueue === []) {
            return new ProcessImportDataResult(200, [
                'message' => __('process.import_all_already_registered'),
                'skipped_already_registered' => count($result->validNumbers),
            ], toEnqueue: [], skippedAlreadyRegistered: count($result->validNumbers));
        }

        return new ProcessImportDataResult(
            status: 202,
            body: [],
            toEnqueue: $toEnqueue,
            organizationId: $data->organization_id,
            fileName: $data->file->getClientOriginalName(),
            skippedAlreadyRegistered: $skippedAlreadyRegistered,
            requestedById: auth()->id(),
        );
    }
}
