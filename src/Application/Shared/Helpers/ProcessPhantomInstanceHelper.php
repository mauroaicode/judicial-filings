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

    private static function sameFolderMetadata(Process $left, Process $right): bool
    {
        return $left->court === $right->court
            && $left->department === $right->department
            && $left->process_date->format('Y-m-d') === $right->process_date->format('Y-m-d');
    }
}
