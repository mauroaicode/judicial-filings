<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Application\Shared\Helpers\StrParseHelper;
use Src\Domain\Process\Models\Process;

class AdminProcessInstanceResource extends Resource
{
    public function __construct(
        public string $id,
        public string $court,
        public int $actions_count,
        public ?string $last_activity_date,
        public ?string $last_api_update,
        public string $status_label,
    ) {}

    public static function fromModel(Process $process): self
    {
        return new self(
            id: $process->id,
            court: StrParseHelper::toTitleCase($process->court) ?? '',
            actions_count: (int) ($process->actions_count ?? 0),
            last_activity_date: $process->last_activity_date
                ? DateFormatHelper::formatDateTimeWithDayOfWeek($process->last_activity_date)
                : null,
            last_api_update: $process->last_api_update
                ? DateFormatHelper::formatDateTime($process->last_api_update)
                : null,
            status_label: self::judicialStatusLabel($process),
        );
    }

    private static function judicialStatusLabel(Process $process): string
    {
        $raw = (string) ($process->status ?? '');

        return match ($raw) {
            'activo', 'active' => (string) __('enums.process_status.active'),
            'inactivo', 'inactive' => (string) __('enums.process_status.inactive'),
            'pending' => (string) __('enums.process_status.pending'),
            'closed', 'cerrado' => (string) __('enums.process_status.closed'),
            default => $raw !== '' ? $raw : '-',
        };
    }
}
