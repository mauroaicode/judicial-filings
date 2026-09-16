<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Services;

use Src\Application\Admin\Process\DTOs\PrivateProcessExcelImportedRowDTO;

/**
 * Publicaciones procesales often lists two distinct actuaciones with the same
 * date, action and annotation (different auto, PDF or parties). This keeps each
 * Excel row importable instead of collapsing them into one.
 */
final class ActuacionImportRowIdentity
{
    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @return list<PrivateProcessExcelImportedRowDTO>
     */
    public function distinguish(array $rows): array
    {
        /** @var array<string, list<int>> $groups */
        $groups = [];

        foreach ($rows as $index => $row) {
            $groups[$this->baseKey($row)][] = $index;
        }

        $distinguished = $rows;

        foreach ($groups as $indexes) {
            $occurrence = 0;
            $total = count($indexes);
            $partiesDiffer = $this->partiesDiffer($rows, $indexes);

            foreach ($indexes as $index) {
                $occurrence++;
                $distinguished[$index] = $this->enrich($rows[$index], $occurrence, $total, $partiesDiffer);
            }
        }

        return $distinguished;
    }

    private function baseKey(PrivateProcessExcelImportedRowDTO $row): string
    {
        $date = $row->registrationDate ?? $row->startDate ?? '';

        return implode('|', [
            $row->processNumber,
            mb_strtolower(trim($row->court)),
            mb_strtolower(trim($row->actionText)),
            mb_strtolower(trim((string) $row->annotation)),
            $date,
        ]);
    }

    /**
     * @param  list<PrivateProcessExcelImportedRowDTO>  $rows
     * @param  list<int>  $indexes
     */
    private function partiesDiffer(array $rows, array $indexes): bool
    {
        $signatures = [];

        foreach ($indexes as $index) {
            $signatures[] = mb_strtolower(trim($rows[$index]->plaintiffsRaw)).'|'.mb_strtolower(trim($rows[$index]->defendantsRaw));
        }

        return count(array_unique($signatures)) > 1;
    }

    private function enrich(
        PrivateProcessExcelImportedRowDTO $row,
        int $occurrence,
        int $total,
        bool $partiesDiffer,
    ): PrivateProcessExcelImportedRowDTO {
        $parts = [];
        $original = trim((string) $row->annotation);

        if ($original !== '') {
            $parts[] = $original;
        }

        if (is_string($row->autoNumber) && trim($row->autoNumber) !== '') {
            $parts[] = 'Auto '.trim($row->autoNumber);
        }

        if (is_string($row->documentName) && trim($row->documentName) !== '') {
            $parts[] = trim($row->documentName);
        }

        if ($partiesDiffer) {
            $parties = trim($row->plaintiffsRaw.' / '.$row->defendantsRaw, ' /');
            if ($parties !== '') {
                $parts[] = $parties;
            }
        }

        if ($total > 1 && ! $this->hasUniqueMarker($row) && ! $partiesDiffer) {
            $parts[] = 'Publicación '.$occurrence;
        }

        $annotation = $parts === [] ? null : implode(' — ', $parts);

        return $row->withAnnotation($annotation);
    }

    private function hasUniqueMarker(PrivateProcessExcelImportedRowDTO $row): bool
    {
        return (is_string($row->documentName) && trim($row->documentName) !== '')
            || (is_string($row->autoNumber) && trim($row->autoNumber) !== '');
    }
}
