<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Formatea fechas para toda la aplicación.
 * - Solo fecha: "25 de Junio de 2026" (es) / "25 June 2026" (en)
 * - Fecha y hora: "25 Junio 2026 2:35pm" (si la hora es 00:00:00 se muestra solo la fecha).
 * - Fecha con día semana: "Domingo, 22 de febrero de 2026 12:17pm".
 */
class DateFormatHelper
{
    /**
     * Formato solo fecha: "25 de Junio de 2026".
     */
    public static function formatDate(CarbonInterface|Carbon|\DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $carbon = $value instanceof Carbon ? $value : \Illuminate\Support\Facades\Date::parse($value);
        $locale = (string) app()->getLocale();
        $carbon = $carbon->locale($locale);

        $format = $locale === 'es' ? 'j \d\e F \d\e Y' : 'F j, Y';

        return $carbon->translatedFormat($format);
    }

    /**
     * Formato fecha y hora: "25 Junio 2026 2:35pm".
     * Si la hora es 00:00:00 se devuelve solo la fecha: "25 de Junio de 2026".
     */
    public static function formatDateTime(CarbonInterface|Carbon|\DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $carbon = $value instanceof Carbon ? $value : \Illuminate\Support\Facades\Date::parse($value);

        $hasTime = $carbon->hour !== 0 || $carbon->minute !== 0 || $carbon->second !== 0;

        if (! $hasTime) {
            return self::formatDate($carbon);
        }

        $locale = (string) app()->getLocale();

        return $carbon->locale($locale)->translatedFormat('j F Y g:ia');
    }

    /**
     * Formato fecha con día de la semana: "Domingo, 22 de febrero de 2026 12:17pm".
     * Si la hora es 00:00:00 se omite la parte horaria: "Domingo, 22 de febrero de 2026".
     */
    public static function formatDateTimeWithDayOfWeek(CarbonInterface|Carbon|\DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $carbon = $value instanceof Carbon ? $value : \Illuminate\Support\Facades\Date::parse($value);
        $locale = (string) app()->getLocale();
        $carbon = $carbon->locale($locale);

        $hasTime = $carbon->hour !== 0 || $carbon->minute !== 0 || $carbon->second !== 0;

        $format = $locale === 'es'
            ? ($hasTime ? 'l, j \d\e F \d\e Y g:ia' : 'l, j \d\e F \d\e Y')
            : ($hasTime ? 'l, F j, Y g:ia' : 'l, F j, Y');

        return ucfirst($carbon->translatedFormat($format));
    }
}
