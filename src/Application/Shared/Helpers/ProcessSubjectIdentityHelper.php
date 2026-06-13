<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\ProcessSubject;

/**
 * Identifies the same procedural subject across judicial instances.
 * Rama Judicial assigns a different idRegSujeto per instance even for the same person.
 */
final class ProcessSubjectIdentityHelper
{
    public static function key(ProcessSubject $subject): string
    {
        return self::keyFromParts(
            (string) $subject->subject_type,
            (string) $subject->name_or_business_name,
            $subject->identification,
        );
    }

    public static function keyFromParts(string $subjectType, string $name, ?string $identification): string
    {
        return mb_strtoupper(trim($subjectType))
            .'|'.mb_strtoupper(trim($name))
            .'|'.mb_strtoupper(trim((string) ($identification ?? '')));
    }

    public static function findCanonicalForRadicado(
        string $processNumber,
        string $subjectType,
        string $name,
        ?string $identification,
    ): ?ProcessSubject {
        $targetKey = self::keyFromParts($subjectType, $name, $identification);

        return ProcessSubject::query()
            ->whereHas('processes', fn ($query) => $query->where('process_number', $processNumber))
            ->get()
            ->first(fn (ProcessSubject $subject): bool => self::key($subject) === $targetKey);
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     * @return Collection<int, ProcessSubject>
     */
    public static function deduplicate(Collection $subjects): Collection
    {
        $seen = [];
        $unique = collect();

        foreach ($subjects as $subject) {
            $identityKey = self::key($subject);

            if (isset($seen[$identityKey])) {
                continue;
            }

            $seen[$identityKey] = true;
            $unique->push($subject);
        }

        return $unique->values();
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     */
    public static function pickCanonical(Collection $subjects): ProcessSubject
    {
        return $subjects->sortBy([
            fn (ProcessSubject $subject): int => $subject->subject_registration_id ?? PHP_INT_MAX,
            fn (ProcessSubject $subject): int => $subject->created_at?->getTimestamp() ?? 0,
            fn (ProcessSubject $subject): string => (string) $subject->id,
        ])->first();
    }
}
