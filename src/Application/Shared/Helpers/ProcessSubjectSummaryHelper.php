<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\ProcessSubject;

final class ProcessSubjectSummaryHelper
{
    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     * @return array{
     *     plaintiffs_count: int,
     *     defendants_count: int,
     *     others_count: int,
     *     subjects_count: int,
     *     plaintiff: string|null,
     *     defendant: string|null,
     *     other_subject: string|null,
     *     plaintiffs: list<string>,
     *     defendants: list<string>,
     *     others: list<string>,
     * }
     */
    public static function summarize(Collection $subjects): array
    {
        $grouped = self::group($subjects);

        $plaintiffs = self::mapSubjectNames($grouped['plaintiffs']);
        $defendants = self::mapSubjectNames($grouped['defendants']);
        $others = self::mapSubjectNames($grouped['others']);

        return [
            'plaintiffs_count' => count($plaintiffs),
            'defendants_count' => count($defendants),
            'others_count' => count($others),
            'subjects_count' => $subjects->count(),
            'plaintiff' => self::formatSummaryName($plaintiffs),
            'defendant' => self::formatSummaryName($defendants),
            'other_subject' => self::formatSummaryName($others),
            'plaintiffs' => $plaintiffs,
            'defendants' => $defendants,
            'others' => $others,
        ];
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     * @return array{
     *     plaintiffs: Collection<int, ProcessSubject>,
     *     defendants: Collection<int, ProcessSubject>,
     *     others: Collection<int, ProcessSubject>,
     * }
     */
    public static function group(Collection $subjects): array
    {
        $plaintiffs = $subjects->filter(
            fn (ProcessSubject $subject): bool => self::isPlaintiffType((string) $subject->subject_type),
        );

        $defendants = $subjects->filter(
            fn (ProcessSubject $subject): bool => ! self::isPlaintiffType((string) $subject->subject_type)
                && self::isDefendantType((string) $subject->subject_type),
        );

        $others = $subjects->filter(
            fn (ProcessSubject $subject): bool => ! self::isPlaintiffType((string) $subject->subject_type)
                && ! self::isDefendantType((string) $subject->subject_type),
        );

        return [
            'plaintiffs' => $plaintiffs->values(),
            'defendants' => $defendants->values(),
            'others' => $others->values(),
        ];
    }

    public static function isPlaintiffType(string $subjectType): bool
    {
        return str_contains(mb_strtoupper($subjectType), mb_strtoupper(ProcessSubject::TYPE_PLAINTIFF));
    }

    public static function isDefendantType(string $subjectType): bool
    {
        return str_contains(mb_strtoupper($subjectType), mb_strtoupper(ProcessSubject::TYPE_DEFENDANT));
    }

    /**
     * @param  Collection<int, ProcessSubject>  $subjects
     * @return list<string>
     */
    private static function mapSubjectNames(Collection $subjects): array
    {
        return $subjects
            ->map(fn (ProcessSubject $subject): string => StrParseHelper::toTitleCase($subject->name_or_business_name) ?? '')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $names
     */
    private static function formatSummaryName(array $names): ?string
    {
        if ($names === []) {
            return null;
        }

        $firstName = $names[0];
        $count = count($names);

        return $count > 1 ? $firstName.' (+'.($count - 1).')' : $firstName;
    }
}
