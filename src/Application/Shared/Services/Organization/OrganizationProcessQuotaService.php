<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Organization;

use Src\Domain\Organization\Models\Organization;
use Src\Domain\Organization\Models\OrganizationSetting;
use Src\Domain\OrganizationProcess\Enums\OrganizationProcessStatus;
use Src\Domain\OrganizationProcess\Models\OrganizationProcess;
use Src\Domain\Process\Models\Process;

/**
 * Enforces per-organization limits on distinct active radicados.
 *
 * Limit resolution:
 *  1. organization_settings.max_active_processes when set (int)
 *  2. Otherwise config('organization.defaults.max_active_processes') from .env
 *  3. null = unlimited
 */
readonly class OrganizationProcessQuotaService
{
    /**
     * Distinct process_number values currently ACTIVE for the organization.
     */
    public function countActiveProcesses(string $organizationId): int
    {
        return (int) Process::query()
            ->join('organization_processes', 'processes.id', '=', 'organization_processes.process_id')
            ->where('organization_processes.organization_id', $organizationId)
            ->where('organization_processes.status', OrganizationProcessStatus::ACTIVE->value)
            ->whereNull('organization_processes.deleted_at')
            ->distinct()
            ->count('processes.process_number');
    }

    /**
     * Global default from config/.env (null = unlimited).
     */
    public function defaultMaxActiveProcesses(): ?int
    {
        $default = config('organization.defaults.max_active_processes');

        if ($default === null || $default === '') {
            return null;
        }

        return max(0, (int) $default);
    }

    /**
     * Raw value stored for the organization, or null when not configured (uses default).
     */
    public function configuredMaxActiveProcesses(string $organizationId): ?int
    {
        $settings = OrganizationSetting::query()
            ->where('organization_id', $organizationId)
            ->first();

        if (! $settings instanceof OrganizationSetting || $settings->max_active_processes === null) {
            return null;
        }

        return max(0, (int) $settings->max_active_processes);
    }

    /**
     * Effective max active radicados, or null when unlimited.
     *
     * - Settings row with int → that limit
     * - Settings null / missing row → config/.env default
     * - Config also null → unlimited
     */
    public function resolveLimit(string $organizationId): ?int
    {
        $configured = $this->configuredMaxActiveProcesses($organizationId);

        if ($configured !== null) {
            return $configured;
        }

        return $this->defaultMaxActiveProcesses();
    }

    /**
     * Remaining slots before hitting the limit, or null when unlimited.
     */
    public function remainingSlots(string $organizationId): ?int
    {
        $limit = $this->resolveLimit($organizationId);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->countActiveProcesses($organizationId));
    }

    /**
     * Quota snapshot for app-user UI (modal, banners, etc.).
     *
     * @return array{
     *     active_processes_count: int,
     *     max_active_processes: int|null,
     *     remaining_slots: int|null,
     *     is_unlimited: bool,
     *     is_at_limit: bool,
     *     can_add_process: bool
     * }
     */
    public function getSummary(string $organizationId): array
    {
        $limit = $this->resolveLimit($organizationId);
        $current = $this->countActiveProcesses($organizationId);
        $remaining = $limit === null ? null : max(0, $limit - $current);
        $isAtLimit = $limit !== null && $current >= $limit;

        return [
            'active_processes_count' => $current,
            'max_active_processes' => $limit,
            'remaining_slots' => $remaining,
            'is_unlimited' => $limit === null,
            'is_at_limit' => $isAtLimit,
            'can_add_process' => ! $isAtLimit,
        ];
    }

    /**
     * Whether the organization already tracks this radicado number as active.
     */
    public function organizationTracksRadicadoAsActive(string $organizationId, string $processNumber): bool
    {
        return OrganizationProcess::query()
            ->join('processes', 'organization_processes.process_id', '=', 'processes.id')
            ->where('organization_processes.organization_id', $organizationId)
            ->where('organization_processes.status', OrganizationProcessStatus::ACTIVE->value)
            ->where('processes.process_number', $processNumber)
            ->exists();
    }

    /**
     * Whether importing/activating this radicado would consume a new active slot.
     */
    public function wouldConsumeNewActiveSlot(string $organizationId, string $processNumber): bool
    {
        return ! $this->organizationTracksRadicadoAsActive($organizationId, $processNumber);
    }

    /**
     * Whether the organization can add $additional new active radicados without exceeding the limit.
     */
    public function canAddProcesses(string $organizationId, int $additional = 1): bool
    {
        if ($additional < 1) {
            return true;
        }

        $remaining = $this->remainingSlots($organizationId);

        return $remaining === null || $remaining >= $additional;
    }

    /**
     * Human-readable reason when a radicado cannot be imported due to quota.
     */
    public function quotaLimitReason(string $organizationId): string
    {
        $limit = $this->resolveLimit($organizationId);
        $current = $this->countActiveProcesses($organizationId);

        return __('process.max_active_processes_import_skipped', [
            'limit' => $limit ?? 0,
            'current' => $current,
        ]);
    }

    /**
     * Splits radicados into those that fit the remaining quota vs. rejected with per-radicado reasons.
     *
     * @param  array<int, string>  $processNumbers
     * @return array{
     *     allowed: array<int, string>,
     *     rejected: array<int, array{process_number: string, reason: string}>
     * }
     */
    public function partitionProcessNumbersByQuota(string $organizationId, array $processNumbers): array
    {
        $remaining = $this->remainingSlots($organizationId);

        if ($remaining === null) {
            return [
                'allowed' => $processNumbers,
                'rejected' => [],
            ];
        }

        $allowed = [];
        $rejected = [];
        $reason = $this->quotaLimitReason($organizationId);
        $pendingNewSlots = 0;
        /** @var array<string, true> $radicadosReservedInBatch */
        $radicadosReservedInBatch = [];

        foreach ($processNumbers as $processNumber) {
            if (
                ! $this->wouldConsumeNewActiveSlot($organizationId, $processNumber)
                || isset($radicadosReservedInBatch[$processNumber])
            ) {
                $allowed[] = $processNumber;
                $radicadosReservedInBatch[$processNumber] = true;

                continue;
            }

            if (($pendingNewSlots + 1) <= $remaining) {
                $allowed[] = $processNumber;
                $pendingNewSlots++;
                $radicadosReservedInBatch[$processNumber] = true;

                continue;
            }

            $rejected[] = [
                'process_number' => $processNumber,
                'reason' => $reason,
            ];
        }

        return [
            'allowed' => $allowed,
            'rejected' => $rejected,
        ];
    }

    /**
     * Abort with 422 when adding $additional new active radicados would exceed the limit.
     */
    public function assertCanAddProcesses(string $organizationId, int $additional = 1): void
    {
        if ($additional < 1) {
            return;
        }

        $limit = $this->resolveLimit($organizationId);

        if ($limit === null) {
            return;
        }

        $current = $this->countActiveProcesses($organizationId);

        if (($current + $additional) > $limit) {
            abort(422, __('process.max_active_processes_reached', [
                'limit' => $limit,
                'current' => $current,
            ]));
        }
    }

    /**
     * Abort when reactivating a process would consume a new active-radicado slot.
     */
    public function assertCanActivateProcess(string $organizationId, string $processId): void
    {
        $process = Process::query()->find($processId);

        if (! $process instanceof Process) {
            return;
        }

        if ($this->organizationAlreadyTracksRadicadoAsActive($organizationId, (string) $process->process_number, $processId)) {
            return;
        }

        $this->assertCanAddProcesses($organizationId, 1);
    }

    /**
     * Ensure a settings row exists (lazy create).
     */
    public function ensureSettings(Organization $organization): OrganizationSetting
    {
        $existing = OrganizationSetting::query()
            ->where('organization_id', $organization->id)
            ->first();

        if ($existing instanceof OrganizationSetting) {
            return $existing;
        }

        return OrganizationSetting::query()->create([
            'organization_id' => $organization->id,
            'max_active_processes' => config('organization.defaults.max_active_processes'),
        ]);
    }

    private function organizationAlreadyTracksRadicadoAsActive(
        string $organizationId,
        string $processNumber,
        string $excludeProcessId,
    ): bool {
        return OrganizationProcess::query()
            ->join('processes', 'organization_processes.process_id', '=', 'processes.id')
            ->where('organization_processes.organization_id', $organizationId)
            ->where('organization_processes.status', OrganizationProcessStatus::ACTIVE->value)
            ->where('processes.process_number', $processNumber)
            ->where('processes.id', '!=', $excludeProcessId)
            ->exists();
    }
}
