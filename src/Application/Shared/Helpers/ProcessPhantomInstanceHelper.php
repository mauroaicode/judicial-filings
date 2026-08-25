<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\Process;

/**
 * Detects duplicate Rama Judicial folders for the same radicado (phantom instances):
 * same despacho/metadata but empty sujetos while another sibling has real data.
 */
final class ProcessPhantomInstanceHelper
{
    public static function hasMeaningfulLitigants(?string $litigants): bool
    {
        if ($litigants === null) {
            return false;
        }

        $trimmed = trim($litigants);

        return $trimmed !== '' && $trimmed !== '---';
    }

    public static function isRichInstance(Process $process): bool
    {
        if ($process->subjects()->exists()) {
            return true;
        }

        return self::hasMeaningfulLitigants($process->litigants);
    }

    /**
     * @param  Collection<int, Process>  $siblings  All instances for the radicado
     */
    public static function isLikelyPhantomDuplicate(Process $process, Collection $siblings): bool
    {
        if (! (bool) config('judicial-sync.skip_phantom_instance_actuaciones', true)) {
            return false;
        }

        if ($siblings->count() <= 1) {
            return false;
        }

        if (self::isRichInstance($process)) {
            return false;
        }

        return $siblings->contains(
            fn (Process $other): bool => $other->id !== $process->id
                && self::sameFolderMetadata($process, $other)
                && self::isRichInstance($other),
        );
    }

    /**
     * Prefer the folder that already holds the radicado history when importing
     * actuaciones Excel (multi-instance / phantom siblings).
     *
     * @param  Collection<int, Process>  $processes
     */
    public static function pickPreferredInstanceForImport(Collection $processes): ?Process
    {
        if ($processes->isEmpty()) {
            return null;
        }

        if ($processes->count() === 1) {
            return $processes->first();
        }

        return $processes
            ->sort(function (Process $left, Process $right): int {
                $leftActions = (int) ($left->actions_count ?? $left->actions()->count());
                $rightActions = (int) ($right->actions_count ?? $right->actions()->count());
                if ($leftActions !== $rightActions) {
                    return $rightActions <=> $leftActions;
                }

                $leftRich = self::isRichInstance($left) ? 1 : 0;
                $rightRich = self::isRichInstance($right) ? 1 : 0;
                if ($leftRich !== $rightRich) {
                    return $rightRich <=> $leftRich;
                }

                $leftActivity = $left->last_activity_date?->format('Y-m-d') ?? '';
                $rightActivity = $right->last_activity_date?->format('Y-m-d') ?? '';
                if ($leftActivity !== $rightActivity) {
                    return $rightActivity <=> $leftActivity;
                }

                return strcmp((string) $left->id, (string) $right->id);
            })
            ->first();
    }

    private static function sameFolderMetadata(Process $left, Process $right): bool
    {
        return $left->court === $right->court
            && $left->department === $right->department
            && $left->process_date->format('Y-m-d') === $right->process_date->format('Y-m-d');
    }
}
