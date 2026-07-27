<?php

declare(strict_types=1);

namespace Src\Domain\Process\Enums;

/**
 * Canonical slugs for rows in {@see \Src\Domain\Process\Models\ProcessDataSource}.
 */
enum ProcessDataSourceSlug: string
{
    case JudicialBranch = 'judicial_branch';

    case Samai = 'samai';

    /** Manual Excel import for small courts / procedural publications (no API sync). */
    case PublicacionesProcesales = 'publicaciones_procesales';

    /**
     * Sources backed by an external consultation API (Rama Judicial, SAMAI, …).
     */
    public function isApiConsultation(): bool
    {
        return match ($this) {
            self::JudicialBranch, self::Samai => true,
            self::PublicacionesProcesales => false,
        };
    }

    /**
     * Sources allowed on admin private Excel import (`processes/private-import`).
     */
    public function allowsPrivateExcelImport(): bool
    {
        return match ($this) {
            self::PublicacionesProcesales, self::Samai => true,
            self::JudicialBranch => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<string>
     */
    public static function apiConsultationValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->isApiConsultation()),
        ));
    }

    /**
     * @return list<string>
     */
    public static function privateExcelImportValues(): array
    {
        return array_values(array_map(
            static fn (self $case): string => $case->value,
            array_filter(self::cases(), static fn (self $case): bool => $case->allowsPrivateExcelImport()),
        ));
    }
}
