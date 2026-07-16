<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Src\Application\Admin\Process\DTOs\ProcessImportDataResult;
use Src\Application\Admin\Process\DTOs\ProcessImportParseResult;
use Src\Domain\Process\Models\Process;

readonly class ProcessImportDataService
{
    /**
     * Parses the Excel, filters already registered radicados and returns the result ready to enqueue.
     *
     * @param  string  $organizationId  Organization identifier
     * @param  UploadedFile  $file  Uploaded Excel file
     * @param  string  $fileName  Original file name for the batch record
     * @param  mixed  $requestedById  User ID who requested the import
     * @param  string  $source  Process data source slug ('judicial_branch' or 'samai')
     */
    public function handle(
        string $organizationId,
        UploadedFile $file,
        string $fileName,
        mixed $requestedById,
        string $source = 'judicial_branch',
    ): ProcessImportDataResult {
        $parsed = $this->parseExcel($file);

        if ($parsed->hasErrors()) {
            return new ProcessImportDataResult(422, [
                'message' => __('process.import_validation_failed'),
                'errors' => ['rows' => $parsed->rowErrors],
            ]);
        }

        if ($parsed->validNumbers === []) {
            return new ProcessImportDataResult(422, [
                'message' => __('process.import_validation_failed'),
                'errors' => ['file' => [__('validation.process_number.regex')]],
            ]);
        }

        $alreadyRegistered = $this->findAlreadyRegistered($parsed->validNumbers, $organizationId);
        $processToEnqueue = $this->resolveToEnqueue($parsed->validNumbers, $alreadyRegistered);
        $skippedAlreadyRegistered = count($parsed->validNumbers) - count($processToEnqueue);

        $this->log('Import started: parsing and enqueue', [
            'valid_numbers_from_excel' => count($parsed->validNumbers),
            'already_registered_for_org' => $alreadyRegistered->count(),
            'to_enqueue' => count($processToEnqueue),
            'organization_id' => $organizationId,
        ]);

        if ($processToEnqueue === []) {
            return new ProcessImportDataResult(200, [
                'message' => __('process.import_all_already_registered'),
                'skipped_already_registered' => count($parsed->validNumbers),
            ], toEnqueue: [], skippedAlreadyRegistered: count($parsed->validNumbers));
        }

        return new ProcessImportDataResult(
            status: 202,
            body: [],
            toEnqueue: $processToEnqueue,
            organizationId: $organizationId,
            fileName: $fileName,
            skippedAlreadyRegistered: $skippedAlreadyRegistered,
            requestedById: $requestedById,
            source: $source,
        );
    }

    /**
     * Instantiates the reader and executes the parse.
     *
     * @param  UploadedFile  $file  Excel file to parse
     */
    private function parseExcel(UploadedFile $file): ProcessImportParseResult
    {
        return (new ProcessImportExcelReader($file))->parse();
    }

    /**
     * Queries the DB for process numbers already linked to the given organization.
     *
     * @param  array<int, string>  $validNumbers  Process numbers from the Excel
     * @param  string  $organizationId  Organization identifier
     * @return Collection<int, string>
     */
    private function findAlreadyRegistered(array $validNumbers, string $organizationId): Collection
    {
        return Process::query()
            ->whereIn('process_number', $validNumbers)
            ->whereHas('organizations', fn (Builder $q) => $q->where('organizations.id', $organizationId))
            ->pluck('process_number');
    }

    /**
     * Returns the difference between all valid numbers and the already registered ones.
     *
     * @param  array<int, string>  $validNumbers  All valid process numbers
     * @param  Collection<int, string>  $alreadyRegistered  Already registered numbers
     * @return array<int, string>
     */
    private function resolveToEnqueue(array $validNumbers, Collection $alreadyRegistered): array
    {
        return array_values(array_diff($validNumbers, $alreadyRegistered->all()));
    }

    /**
     * Writes an info log entry to the configured import channel.
     *
     * @param  string  $message  Log message
     * @param  array<string, mixed>  $context  Additional context data
     */
    private function log(string $message, array $context = []): void
    {
        Log::channel(config('process-import.log_channel', 'process_import'))
            ->info($message, $context);
    }
}
