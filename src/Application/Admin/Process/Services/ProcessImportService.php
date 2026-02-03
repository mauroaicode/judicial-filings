<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\Application\Admin\Process\Imports\ProcessActionImport;
use Src\Application\Admin\Process\Imports\ProcessImport;
use Src\Application\Admin\Process\Imports\ProcessOrganizationImport;
use Src\Application\Admin\Process\Imports\ProcessSubjectImport;
use Src\Application\Admin\Process\Validation\ProcessImportValidator;
use Src\Application\Shared\Services\Excel\ExcelImportService;
use Src\Application\Shared\Traits\ParseDateTrait;
use Src\Domain\Organization\Models\Organization;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;
use Src\Domain\Process\Models\ProcessSubject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

class ProcessImportService
{
    use ParseDateTrait;

    /** @var array<string, Process> */
    private array $processCache = [];

    /** @var array{processed: int, created: int, updated: int, errors: array<int, array{row: int, message: string}>} */
    private array $stats = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'errors' => [],
    ];

    /**
     * Handle the Excel import: validate first, then import if valid.
     *
     * @return array{success: bool, message: string, stats: array}
     */
    public function handle(UploadedFile $file): array
    {
        $validator = new ProcessImportValidator;
        $validationResult = $validator->validate($file);

        if (! $validationResult->valid) {
            $errors = $validationResult->errors;
            $message = $this->validationFailureMessage($errors);

            return [
                'success' => false,
                'message' => $message,
                'stats' => [
                    'validated' => false,
                    'succeeded' => 0,
                    'failed' => count($errors),
                    'total' => 0,
                    'errors' => array_map(fn (array $e): array => [
                        'rule' => $e['rule'],
                        'message' => $e['message'],
                        'details' => $e['details'] ?? [],
                    ], $errors),
                    'validation_errors' => $errors,
                ],
            ];
        }

        try {
            $this->importProcesses($file);
            $this->importActions($file);
            $this->importSubjects($file);
            $this->importOrganizations($file);
        } catch (Throwable $e) {
            Log::error('Process import error: '.$e->getMessage(), [
                'file' => $file->getClientOriginalName(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => __('process.import_failed', ['error' => $e->getMessage()]),
                'stats' => $this->buildStatsResponse(),
            ];
        }

        $errorCount = count($this->stats['errors']);

        return [
            'success' => $errorCount === 0,
            'message' => $errorCount === 0
                ? __('process.import_successful')
                : __('process.import_completed_with_errors'),
            'stats' => $this->buildStatsResponse(),
        ];
    }

    private function validationFailureMessage(array $errors): string
    {
        $processHasOrgs = array_filter($errors, fn (array $e): bool => $e['rule'] === 'process_has_organizations');
        if ($processHasOrgs !== []) {
            $first = reset($processHasOrgs);
            $details = $first['details'] ?? [];
            $numbers = $details['process_numbers_without_organization'] ?? null;
            if (is_array($numbers) && $numbers !== []) {
                return __('process.validation_processes_without_organization_summary', [
                    'numbers' => implode(', ', $numbers),
                ]);
            }

            $numbers = array_map(fn (array $e) => $e['details']['process_number'] ?? '', $processHasOrgs);
            $numbers = array_filter($numbers);
            if ($numbers !== []) {
                return __('process.validation_processes_without_organization_summary', [
                    'numbers' => implode(', ', $numbers),
                ]);
            }
        }

        return __('process.import_validation_failed');
    }

    /** @return array{validated: bool, succeeded: int, failed: int, total: int, processed: int, created: int, updated: int, errors: array} */
    private function buildStatsResponse(): array
    {
        $errorCount = count($this->stats['errors']);
        $succeeded = max(0, $this->stats['processed'] - $errorCount);

        return [
            'validated' => true,
            'succeeded' => $succeeded,
            'failed' => $errorCount,
            'total' => $this->stats['processed'],
            'processed' => $this->stats['processed'],
            'created' => $this->stats['created'],
            'updated' => $this->stats['updated'],
            'errors' => array_map(fn (array $e): array => [
                'row' => $e['row'],
                'message' => $e['message'],
            ], $this->stats['errors']),
        ];
    }

    private function importProcesses(UploadedFile $file): void
    {
        try {
            ExcelImportService::import(
                new ProcessImport($this),
                $file,
                'Procesos',
                'xlsx'
            );
        } catch (Throwable $e) {
            Log::error('Error importing processes sheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /** @param  Collection<int, Collection<string, mixed>>  $rows */
    public function processProcessRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $index => $row) {
                try {
                    $this->createOrUpdateProcess($row);
                    $this->stats['processed']++;
                } catch (Throwable $e) {
                    $this->stats['errors'][] = [
                        'row' => $index + 2,
                        'message' => $e->getMessage(),
                    ];
                    Log::warning('Process import row error', [
                        'row' => $index + 2,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    /** @param  Collection<string, mixed>  $row */
    private function createOrUpdateProcess(Collection $row): void
    {
        $processNumber = (string) $row->get('process_number');

        if (isset($this->processCache[$processNumber])) {
            $process = $this->processCache[$processNumber];
            $this->updateProcess($process, $row);
            $this->stats['updated']++;

            return;
        }

        $process = Process::query()->whereProcessNumber($processNumber)->first();

        if ($process) {
            $this->processCache[$processNumber] = $process;
            $this->updateProcess($process, $row);
            $this->stats['updated']++;

            return;
        }

        $process = $this->createProcess($row);
        $this->processCache[$processNumber] = $process;
        $this->stats['created']++;
    }

    /** @param  Collection<string, mixed>  $row */
    private function createProcess(Collection $row): Process
    {
        return Process::query()->create([
            'process_id' => (int) $row->get('process_id'),
            'process_number' => (string) $row->get('process_number'),
            'court' => (string) $row->get('court'),
            'department' => (string) $row->get('department'),
            'process_type' => (string) $row->get('process_type'),
            'process_class' => (string) $row->get('process_class'),
            'subclass_process' => $row->get('subclass_process') ? (string) $row->get('subclass_process') : null,
            'litigants' => $row->get('litigants') ? (string) $row->get('litigants') : null,
            'process_date' => $this->parseDate($row->get('process_date')) ?? now()->toDateString(),
            'last_activity_date' => $row->get('last_activity_date') ? $this->parseDate($row->get('last_activity_date')) : null,
            'location' => $row->get('location') ? (string) $row->get('location') : null,
            'filing_content' => $row->get('filing_content') ? (string) $row->get('filing_content') : null,
            'is_private' => $this->parseBoolean($row->get('is_private')),
            'has_multiple_instances' => $this->parseBoolean($row->get('has_multiple_instances')),
            'last_api_update' => now(),
        ]);
    }

    /** @param  Collection<string, mixed>  $row */
    private function updateProcess(Process $process, Collection $row): void
    {
        $process->update([
            'process_id' => (int) $row->get('process_id'),
            'court' => (string) $row->get('court'),
            'department' => (string) $row->get('department'),
            'process_type' => (string) $row->get('process_type'),
            'process_class' => (string) $row->get('process_class'),
            'subclass_process' => $row->get('subclass_process') ? (string) $row->get('subclass_process') : null,
            'litigants' => $row->get('litigants') ? (string) $row->get('litigants') : null,
            'process_date' => $this->parseDate($row->get('process_date')) ?? $process->process_date->toDateString(),
            'last_activity_date' => $row->get('last_activity_date') ? $this->parseDate($row->get('last_activity_date')) : $process->last_activity_date?->toDateString(),
            'location' => $row->get('location') ? (string) $row->get('location') : null,
            'filing_content' => $row->get('filing_content') ? (string) $row->get('filing_content') : null,
            'is_private' => $this->parseBoolean($row->get('is_private')),
            'has_multiple_instances' => $this->parseBoolean($row->get('has_multiple_instances')),
            'last_api_update' => now(),
        ]);
    }

    private function importActions(UploadedFile $file): void
    {
        try {
            ExcelImportService::import(
                new ProcessActionImport($this),
                $file,
                'Actuaciones',
                'xlsx'
            );
        } catch (Throwable $e) {
            Log::error('Error importing Actuaciones sheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /** @param  Collection<int, Collection<string, mixed>>  $rows */
    public function processActionRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows): void {
            $actionsToInsert = [];

            foreach ($rows as $index => $row) {
                try {
                    $process = $this->getProcessByNumber((string) $row->get('process_number'));

                    if (! $process instanceof \Src\Domain\Process\Models\Process) {
                        $this->stats['errors'][] = [
                            'row' => $index + 2,
                            'message' => __('process.process_not_found_for_action', ['number' => $row->get('process_number')]),
                        ];

                        continue;
                    }

                    $actionRegistrationId = (int) $row->get('action_registration_id');
                    $exists = ProcessAction::query()
                        ->whereProcessAndRegistrationId($process->id, $actionRegistrationId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $actionsToInsert[] = [
                        'id' => (string) Str::uuid(),
                        'process_id' => $process->id,
                        'action_registration_id' => $actionRegistrationId,
                        'action_date' => $this->parseDate($row->get('action_date')),
                        'action' => (string) $row->get('action'),
                        'annotation' => $row->get('annotation') ? (string) $row->get('annotation') : null,
                        'start_date' => $row->get('start_date') ? $this->parseDate($row->get('start_date')) : null,
                        'end_date' => $row->get('end_date') ? $this->parseDate($row->get('end_date')) : null,
                        'registration_date' => $this->parseDate($row->get('registration_date')),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } catch (Throwable $e) {
                    $this->stats['errors'][] = [
                        'row' => $index + 2,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if ($actionsToInsert !== []) {
                ProcessAction::query()->insert($actionsToInsert);
            }
        });
    }

    private function importSubjects(UploadedFile $file): void
    {
        try {
            ExcelImportService::import(
                new ProcessSubjectImport($this),
                $file,
                'Sujetos',
                'xlsx'
            );
        } catch (Throwable $e) {
            Log::error('Error importing Sujetos sheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /** @param  Collection<int, Collection<string, mixed>>  $rows */
    public function processSubjectRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows): void {
            $subjectsToInsert = [];

            foreach ($rows as $index => $row) {
                try {
                    $process = $this->getProcessByNumber((string) $row->get('process_number'));

                    if (! $process instanceof \Src\Domain\Process\Models\Process) {
                        $this->stats['errors'][] = [
                            'row' => $index + 2,
                            'message' => __('process.process_not_found_for_subject', ['number' => $row->get('process_number')]),
                        ];

                        continue;
                    }

                    $subjectRegistrationId = (int) $row->get('subject_registration_id');
                    $exists = ProcessSubject::query()
                        ->whereProcessAndRegistrationId($process->id, $subjectRegistrationId)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $subjectsToInsert[] = [
                        'id' => (string) Str::uuid(),
                        'process_id' => $process->id,
                        'subject_registration_id' => $subjectRegistrationId,
                        'subject_type' => (string) $row->get('subject_type'),
                        'is_cited' => $this->parseBoolean($row->get('is_cited')),
                        'identification' => $row->get('identification') ? (string) $row->get('identification') : null,
                        'name_or_business_name' => (string) $row->get('name_or_business_name'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                } catch (Throwable $e) {
                    $this->stats['errors'][] = [
                        'row' => $index + 2,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            if ($subjectsToInsert !== []) {
                ProcessSubject::query()->insert($subjectsToInsert);
            }
        });
    }

    private function importOrganizations(UploadedFile $file): void
    {
        try {
            ExcelImportService::import(
                new ProcessOrganizationImport($this),
                $file,
                'Organizaciones',
                'xlsx'
            );
        } catch (Throwable $e) {
            Log::error('Error importing Organizaciones sheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /** @param  Collection<int, Collection<string, mixed>>  $rows */
    public function processOrganizationRows(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($rows): void {
            foreach ($rows as $index => $row) {
                try {
                    $process = $this->getProcessByNumber((string) $row->get('process_number'));

                    if (! $process instanceof \Src\Domain\Process\Models\Process) {
                        $this->stats['errors'][] = [
                            'row' => $index + 2,
                            'message' => __('process.process_not_found_for_organization', ['number' => $row->get('process_number')]),
                        ];

                        continue;
                    }

                    $organizationId = (string) $row->get('organization_id');
                    $organization = Organization::query()->find($organizationId);

                    if (! $organization) {
                        $this->stats['errors'][] = [
                            'row' => $index + 2,
                            'message' => __('process.organization_not_found', ['id' => $organizationId]),
                        ];

                        continue;
                    }

                    $interestDate = $row->has('interest_date') && $row->get('interest_date')
                        ? $this->parseDate($row->get('interest_date'))
                        : null;

                    $process->organizations()->syncWithoutDetaching([
                        $organizationId => [
                            'interest_date' => $interestDate ?? now()->toDateString(),
                            'is_active' => $this->parseBoolean($row->get('is_active') ?? true),
                        ],
                    ]);
                } catch (Throwable $e) {
                    $this->stats['errors'][] = [
                        'row' => $index + 2,
                        'message' => $e->getMessage(),
                    ];
                    Log::error('Error processing organization row', [
                        'row' => $row->toArray(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    private function getProcessByNumber(string $processNumber): ?Process
    {
        if (isset($this->processCache[$processNumber])) {
            return $this->processCache[$processNumber];
        }

        $process = Process::query()->whereProcessNumber($processNumber)->first();

        if ($process) {
            $this->processCache[$processNumber] = $process;
        }

        return $process;
    }

    private function parseBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return strtoupper($value) === 'TRUE' || $value === '1';
        }

        return (bool) $value;
    }
}
