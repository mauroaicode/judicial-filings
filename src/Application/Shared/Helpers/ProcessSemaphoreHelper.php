<?php

declare(strict_types=1);

namespace Src\Application\Shared\Helpers;

use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;

final class ProcessSemaphoreHelper
{
    public const REASON_SUSPENSION_TASK = 'suspension_task';

    /**
     * Resolve semaphore display state for the API (extensible beyond suspension).
     *
     * @return array{paused: bool, reason: string|null, message: string|null}
     */
    public static function resolve(?OrganizationProcessStatus $status): array
    {
        if ($status === OrganizationProcessStatus::SUSPENDED) {
            return [
                'paused' => true,
                'reason' => self::REASON_SUSPENSION_TASK,
                'message' => __('process.semaphore_paused_by_suspension'),
            ];
        }

        return [
            'paused' => false,
            'reason' => null,
            'message' => null,
        ];
    }

    /**
     * Inactivity alert colors must not be shown while the semaphore is paused.
     */
    public static function resolveAlertLevel(
        OrganizationProcessStatus $status,
        ?string $calculatedAlertLevel,
    ): ?string {
        if (self::resolve($status)['paused']) {
            return null;
        }

        return $calculatedAlertLevel;
    }
}
