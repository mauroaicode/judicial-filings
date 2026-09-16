<?php

declare(strict_types=1);

namespace Src\Application\Admin\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ManualRegistrationRequest;

class AdminManualRegistrationRequestResource extends Resource
{
    /**
     * @param  list<array{name: string, identification: string|null}>  $plaintiffs
     * @param  list<array{name: string, identification: string|null}>  $defendants
     * @param  list<array{name: string, identification: string|null}>  $other_subjects
     */
    public function __construct(
        public string $id,
        public string $process_number,
        public string $reason,
        public string $reason_label,
        public string $status,
        public ?string $lawyer_role,
        public ?string $process_class,
        public ?string $court,
        public ?string $speaker,
        public ?string $subclass_process,
        public ?string $location,
        public array $plaintiffs,
        public array $defendants,
        public array $other_subjects,
        public int $unassigned_actions_count,
        public bool $discord_notified,
        public string $organization_id,
        public string $organization_name,
        public string $app_user_id,
        public string $requested_by_name,
        public ?string $requested_by_identification,
        public ?string $requested_by_email,
        public string $created_at,
        public ?string $resolved_at,
    ) {}

    public static function fromModel(ManualRegistrationRequest $request): self
    {
        $request->loadMissing(['appUser', 'organization']);

        $user = $request->appUser;
        if ($user === null) {
            $name = '—';
            $identification = null;
            $email = null;
        } else {
            $name = trim($user->name.' '.$user->last_name) ?: '—';
            $identification = $user->identification;
            $email = $user->email;
        }

        return new self(
            id: $request->id,
            process_number: $request->process_number,
            reason: $request->reason->value,
            reason_label: $request->reason->label(),
            status: $request->status->value,
            lawyer_role: $request->lawyer_role?->value,
            process_class: $request->process_class,
            court: $request->court,
            speaker: $request->speaker,
            subclass_process: $request->subclass_process,
            location: $request->location,
            plaintiffs: $request->plaintiffs ?? [],
            defendants: $request->defendants ?? [],
            other_subjects: $request->other_subjects ?? [],
            unassigned_actions_count: $request->unassigned_actions_count,
            discord_notified: $request->discord_notified,
            organization_id: $request->organization_id,
            organization_name: $request->organization?->name ?: '—',
            app_user_id: $request->app_user_id,
            requested_by_name: $name,
            requested_by_identification: $identification,
            requested_by_email: $email,
            created_at: DateFormatHelper::formatDateTime($request->created_at),
            resolved_at: $request->resolved_at
                ? DateFormatHelper::formatDateTime($request->resolved_at)
                : null,
        );
    }
}
