<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;

readonly class FindCourtDirectoryByDeskCodeService
{
    /**
     * @return Collection<int, CourtDirectoryEntry>
     */
    public function handle(string $deskCode): Collection
    {
        $digits = preg_replace('/\D/', '', $deskCode) ?? '';
        if (strlen($digits) === 23) {
            $digits = substr($digits, 0, 12);
        }

        if (preg_match('/^\d{12}$/', $digits) !== 1) {
            throw ValidationException::withMessages([
                'desk_code' => 'El código del despacho debe tener 12 dígitos.',
            ]);
        }

        return CourtDirectoryEntry::query()
            ->where('desk_code', $digits)
            ->orderByDesc('is_active')
            ->orderBy('email')
            ->get();
    }
}
