<?php

declare(strict_types=1);

namespace Src\Application\Shared\Traits;

use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;

trait ParseDateTrait
{
    /**
     * Parse date string to formatted date string or return null.
     *
     * @param  string|null  $date  The date string to parse.
     */
    protected function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        try {
            return Date::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::warning("Error parsing date: {$date}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
