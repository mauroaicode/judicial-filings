<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

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

        $carbon = $value instanceof Carbon ? $value : Date::parse($value);
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

        $carbon = $value instanceof Carbon ? $value : Date::parse($value);

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

        $carbon = $value instanceof Carbon ? $value : Date::parse($value);
        $locale = (string) app()->getLocale();
        $carbon = $carbon->locale($locale);

        $hasTime = $carbon->hour !== 0 || $carbon->minute !== 0 || $carbon->second !== 0;

        $format = $locale === 'es'
            ? ($hasTime ? 'l, j \d\e F \d\e Y g:ia' : 'l, j \d\e F \d\e Y')
            : ($hasTime ? 'l, F j, Y g:ia' : 'l, F j, Y');

        return ucfirst($carbon->translatedFormat($format));
    }

    /**
     * Formato solo fecha con día de la semana: "Viernes, 12 de Junio de 2026".
     */
    public static function formatDateWithDayOfWeek(CarbonInterface|Carbon|\DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $carbon = $value instanceof Carbon ? $value : Date::parse($value);
        $locale = (string) app()->getLocale();
        $carbon = $carbon->locale($locale);

        if ($locale === 'es') {
            return sprintf(
                '%s, %d de %s de %d',
                ucfirst($carbon->translatedFormat('l')),
                $carbon->day,
                ucfirst($carbon->translatedFormat('F')),
                $carbon->year,
            );
        }

        return $carbon->translatedFormat('l, F j, Y');
    }

    /**
     * Determina el periodo del día (Mañana, Tarde, Noche) según la hora.
     */
    public static function getPeriodFromHour(int $hour): string
    {
        return match (true) {
            $hour >= 0 && $hour < 12 => __('notification.morning'),
            $hour >= 12 && $hour < 18 => __('notification.afternoon'),
            default => __('notification.night'),
        };
    }

    /**
     * Formato "17 de abril de 2026 5:48pm".
     */
    public static function formatDateWithTime(CarbonInterface|Carbon|\DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $carbon = $value instanceof Carbon ? $value : Date::parse($value);
        $locale = (string) app()->getLocale();
        $carbon = $carbon->locale($locale);

        $format = $locale === 'es' ? 'j \d\e F \d\e Y g:ia' : 'F j, Y g:ia';

        return $carbon->translatedFormat($format);
    }
}
