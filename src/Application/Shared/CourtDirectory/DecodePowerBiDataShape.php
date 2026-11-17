<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory;

use Src\Domain\CourtDirectory\CourtDirectoryRow;

/**
 * Expande la respuesta comprimida (DSR) de un reporte público de Power BI.
 */
class DecodePowerBiDataShape
{
    private const COLUMN_COUNT = 8;

    /**
     * @var array<string, string>
     */
    private const FIELDS_BY_PROPERTY = [
        'EMAIL' => 'email',
        'NOMBRE' => 'name',
        'EMPRESA' => 'corporation',
        'DEPARTAMENTO' => 'specialty',
        'Estado Provincia' => 'department',
        'CIUDAD' => 'city',
        'TIPO DE CUENTA' => 'account_type',
        'CODIGO DE DESPACHO' => 'desk_code',
    ];

    /**
     * @param  array<string, mixed>  $queryResponse
     * @param  list<string>  $selectProperties
     * @return list<CourtDirectoryRow>
     */
    public function decode(array $queryResponse, array $selectProperties): array
    {
        $columnCount = count($selectProperties);
        if ($columnCount !== self::COLUMN_COUNT) {
            throw new CourtDirectorySyncException('La tabla de correos no tiene las columnas esperadas.');
        }

        foreach (array_keys(self::FIELDS_BY_PROPERTY) as $property) {
            if (! in_array($property, $selectProperties, true)) {
                throw new CourtDirectorySyncException("La tabla de correos no incluye la columna {$property}.");
            }
        }

        $dataSet = $queryResponse['results'][0]['result']['data']['dsr']['DS'][0] ?? null;
        if (! is_array($dataSet)) {
            throw new CourtDirectorySyncException('La respuesta del directorio no trae filas.');
        }

        $dictionaries = $dataSet['ValueDicts'] ?? null;
        $rows = $dataSet['PH'][0]['DM0'] ?? null;
        if (! is_array($dictionaries) || ! is_array($rows)) {
            throw new CourtDirectorySyncException('La respuesta del directorio no trae filas.');
        }

        $decoded = [];
        $previous = array_fill(0, $columnCount, null);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                throw new CourtDirectorySyncException('Una fila del directorio no tiene el formato esperado.');
            }

            $cells = $this->expandRow($row, $dictionaries, $previous, $columnCount);
            $previous = $cells;
            $mapped = $this->mapRow($cells, $selectProperties);
            if ($mapped instanceof CourtDirectoryRow) {
                $decoded[] = $mapped;
            }
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<mixed>  $dictionaries
     * @param  list<string|null>  $previous
     * @return list<string|null>
     */
    private function expandRow(array $row, array $dictionaries, array $previous, int $columnCount): array
    {
        $values = $row['C'] ?? [];
        if (! is_array($values)) {
            throw new CourtDirectorySyncException('Una fila del directorio no tiene el formato esperado.');
        }

        $repeatMask = (int) ($row['R'] ?? 0);
        $expanded = [];
        $cursor = 0;

        for ($column = 0; $column < $columnCount; $column++) {
            if (($repeatMask & (1 << $column)) !== 0) {
                $expanded[] = $previous[$column];

                continue;
            }

            if (! array_key_exists($cursor, $values)) {
                throw new CourtDirectorySyncException('Una fila del directorio llegó incompleta.');
            }

            $expanded[] = $this->resolveCell($dictionaries, $column, $values[$cursor]);
            $cursor++;
        }

        return $expanded;
    }

    /**
     * @param  array<mixed>  $dictionaries
     */
    private function resolveCell(array $dictionaries, int $column, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            $dictionary = $dictionaries['D'.$column] ?? null;
            if (! is_array($dictionary) || ! array_key_exists($value, $dictionary) || ! is_scalar($dictionary[$value])) {
                throw new CourtDirectorySyncException("Índice {$value} fuera del diccionario D{$column}.");
            }

            return $this->blankToNull(trim((string) $dictionary[$value]));
        }

        if (is_string($value)) {
            return $this->blankToNull(trim($value));
        }

        throw new CourtDirectorySyncException('Una celda del directorio tiene un tipo no soportado.');
    }

    /**
     * @param  list<string|null>  $cells
     * @param  list<string>  $selectProperties
     */
    private function mapRow(array $cells, array $selectProperties): ?CourtDirectoryRow
    {
        $fields = [];
        foreach ($selectProperties as $index => $property) {
            $field = self::FIELDS_BY_PROPERTY[$property] ?? null;
            if ($field === null) {
                continue;
            }

            $fields[$field] = $cells[$index] ?? null;
        }

        $email = mb_strtolower((string) ($fields['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        $deskCode = $fields['desk_code'] ?? null;
        if (! is_string($deskCode) || preg_match('/^\d{12}$/', $deskCode) !== 1) {
            $deskCode = null;
        }

        return new CourtDirectoryRow(
            email: $email,
            name: $this->limit($fields['name'] ?? '', 500) ?? '',
            corporation: $this->limit($fields['corporation'] ?? null, 255),
            specialty: $this->limit($fields['specialty'] ?? null, 255),
            department: $this->limit($fields['department'] ?? null, 255),
            city: $this->limit($fields['city'] ?? null, 255),
            accountType: $this->limit($fields['account_type'] ?? null, 255),
            deskCode: $deskCode,
        );
    }

    private function blankToNull(string $value): ?string
    {
        return $value === '' ? null : $value;
    }

    private function limit(?string $value, int $length): ?string
    {
        if ($value === null || $value === '') {
            return $value === null ? null : '';
        }

        return mb_substr($value, 0, $length);
    }
}
