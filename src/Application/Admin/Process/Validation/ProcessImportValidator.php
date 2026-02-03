<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Validation;

use Src\Application\Admin\Process\Validation\Contracts\ImportValidationRule;
use Src\Application\Admin\Process\Validation\Rules\OrganizationsExistRule;
use Src\Application\Admin\Process\Validation\Rules\ProcessHasOrganizationsRule;
use Src\Application\Admin\Process\Validation\Rules\ProcessWithActionsHasSubjectsRule;
use Src\Application\Admin\Process\Validation\Rules\ProcessWithSubjectsHasActionsRule;
use Src\Application\Shared\Services\Excel\ExcelImportService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

/**
 * Valida el contenido del Excel antes de ejecutar la importación.
 * Si la validación falla, no se debe persistir nada.
 */
final class ProcessImportValidator
{
    private const SHEETS = ['Procesos', 'Actuaciones', 'Sujetos', 'Organizaciones'];

    /** @var list<ImportValidationRule> */
    private array $rules;

    public function __construct(ImportValidationRule ...$rules)
    {
        $this->rules = $rules !== [] ? $rules : $this->defaultRules();
    }

    /**
     * Reglas por defecto: organizaciones existen, cada proceso tiene organizaciones,
     * procesos con actuaciones tienen sujetos, procesos con sujetos tienen actuaciones.
     *
     * @return list<ImportValidationRule>
     */
    private function defaultRules(): array
    {
        return [
            new OrganizationsExistRule,
            new ProcessHasOrganizationsRule,
            new ProcessWithActionsHasSubjectsRule,
            new ProcessWithSubjectsHasActionsRule,
        ];
    }

    /**
     * Agregar una regla de validación (fluent).
     */
    public function addRule(ImportValidationRule $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    /**
     * Lee el archivo, construye los datos filtrados y ejecuta todas las reglas.
     */
    public function validate(UploadedFile $file): ProcessImportValidationResult
    {
        try {
            $data = $this->readAndFilterSheets($file);
        } catch (Throwable $e) {
            return ProcessImportValidationResult::fail([
                [
                    'rule' => 'read_file',
                    'message' => __('process.import_failed', ['error' => $e->getMessage()]),
                    'details' => [],
                ],
            ]);
        }

        $allErrors = [];
        foreach ($this->rules as $rule) {
            $errors = $rule->validate($data);
            foreach ($errors as $err) {
                $allErrors[] = [
                    'rule' => $rule->ruleName(),
                    'message' => $err['message'],
                    'details' => $err['details'] ?? [],
                ];
            }
        }

        return $allErrors === []
            ? ProcessImportValidationResult::ok()
            : ProcessImportValidationResult::fail($allErrors);
    }

    /**
     * Lee las 4 hojas y devuelve un DTO con las filas filtradas por tipo.
     */
    private function readAndFilterSheets(UploadedFile $file): ProcessImportDataForValidation
    {
        $processRows = collect();
        $actionRows = collect();
        $subjectRows = collect();
        $organizationRows = collect();

        foreach (self::SHEETS as $sheetName) {
            $raw = ExcelImportService::readSheetToCollection($file, $sheetName, 'xlsx');
            match ($sheetName) {
                'Procesos' => $processRows = ProcessImportValidationRowsFilter::processRows($raw),
                'Actuaciones' => $actionRows = ProcessImportValidationRowsFilter::actionRows($raw),
                'Sujetos' => $subjectRows = ProcessImportValidationRowsFilter::subjectRows($raw),
                'Organizaciones' => $organizationRows = ProcessImportValidationRowsFilter::organizationRows($raw),
            };
        }

        return new ProcessImportDataForValidation(
            $processRows,
            $actionRows,
            $subjectRows,
            $organizationRows,
        );
    }
}
