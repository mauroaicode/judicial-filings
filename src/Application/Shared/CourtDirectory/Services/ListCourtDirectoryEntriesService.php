<?php

declare(strict_types=1);

namespace Src\Application\Shared\CourtDirectory\Services;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\Domain\CourtDirectory\Models\CourtDirectoryEntry;

readonly class ListCourtDirectoryEntriesService
{
    /**
     * @return LengthAwarePaginator<int, CourtDirectoryEntry>
     */
    public function handle(?string $search, ?string $department, ?string $city, ?string $specialty): LengthAwarePaginator
    {
        $search = trim((string) $search);
        $department = $this->blankToNull($department);
        $city = $this->blankToNull($city);
        $specialty = $this->blankToNull($specialty);

        $query = CourtDirectoryEntry::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($department !== null) {
            $query->where('department', $department);
        }

        if ($city !== null) {
            $query->where('city', $city);
        }

        if ($specialty !== null) {
            $query->where('specialty', $specialty);
        }

        if (mb_strlen($search) < 2 && $department === null && $city === null && $specialty === null) {
            return $query->whereRaw('0 = 1')->paginate(20);
        }

        if (mb_strlen($search) >= 2) {
            $like = '%'.$this->escapeLike($search).'%';
            $digits = preg_replace('/\D/', '', $search) ?? '';

            $query->where(function (Builder $entries) use ($like, $digits): void {
                $entries->where('name', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhere('department', 'like', $like)
                    ->orWhere('specialty', 'like', $like)
                    ->orWhere('corporation', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('desk_code', 'like', $like);

                if (strlen($digits) >= 2) {
                    $entries->orWhere('desk_code', 'like', '%'.$digits.'%');
                }
            });
        }

        return $query->paginate(20);
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}
