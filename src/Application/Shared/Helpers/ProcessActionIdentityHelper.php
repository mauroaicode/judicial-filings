<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Illuminate\Support\Collection;
use Src\Domain\Process\Models\Process;
use Src\Domain\Process\Models\ProcessAction;

/**
 * Identifies the same judicial actuación across duplicate Rama Judicial folders
 * (same radicado, different idProceso / idRegActuacion).
 */
final class ProcessActionIdentityHelper
{
    /**
     * @param  array<string, mixed>  $attributes  ProcessAction fillable attributes (dates as Y-m-d strings)
     */
    public static function fingerprintFromAttributes(array $attributes): string
    {
        $actionDate = $attributes['action_date'];
        $registrationDate = $attributes['registration_date'];

        return self::fingerprintFromParts(
            is_string($actionDate) ? $actionDate : (string) $actionDate,
            (string) ($attributes['action'] ?? ''),
            isset($attributes['annotation']) ? (string) $attributes['annotation'] : null,
            is_string($registrationDate) ? $registrationDate : (string) $registrationDate,
        );
    }

    public static function fingerprint(ProcessAction $action): string
    {
        return self::fingerprintFromParts(
            $action->action_date->format('Y-m-d'),
            (string) $action->action,
            $action->annotation,
            $action->registration_date->format('Y-m-d'),
        );
    }

    public static function fingerprintFromParts(
        string $actionDate,
        string $action,
        ?string $annotation,
        string $registrationDate,
    ): string {
        return mb_strtoupper(trim($actionDate))
            .'|'.mb_strtoupper(trim($action))
            .'|'.mb_strtoupper(trim($annotation ?? ''))
            .'|'.mb_strtoupper(trim($registrationDate));
    }

    /**
     * @param  Collection<int, ProcessAction>  $actions
     * @param  Collection<int, Process>  $processes
     */
    public static function pickCanonical(Collection $actions, Collection $processes): ProcessAction
    {
        $richProcessIds = $processes
            ->filter(fn (Process $process): bool => ProcessPhantomInstanceHelper::isRichInstance($process))
            ->map(fn (Process $process): string => (string) $process->id)
            ->values()
            ->all();

        $canonical = $actions->first();
        if ($canonical === null) {
            throw new \InvalidArgumentException('Cannot pick canonical action from an empty collection.');
        }

        foreach ($actions as $candidate) {
            if (self::isPreferredCanonical($candidate, $canonical, $richProcessIds)) {
                $canonical = $candidate;
            }
        }

        return $canonical;
    }

    /**
     * @param  list<string>  $richProcessIds
     */
    private static function isPreferredCanonical(
        ProcessAction $candidate,
        ProcessAction $current,
        array $richProcessIds,
    ): bool {
        $candidateRich = in_array($candidate->process_id, $richProcessIds, true);
        $currentRich = in_array($current->process_id, $richProcessIds, true);

        if ($candidateRich !== $currentRich) {
            return $candidateRich;
        }

        if ($candidate->action_registration_id !== $current->action_registration_id) {
            return $candidate->action_registration_id < $current->action_registration_id;
        }

        if ($candidate->created_at->getTimestamp() !== $current->created_at->getTimestamp()) {
            return $candidate->created_at->getTimestamp() < $current->created_at->getTimestamp();
        }

        return (string) $candidate->id < (string) $current->id;
    }
}
