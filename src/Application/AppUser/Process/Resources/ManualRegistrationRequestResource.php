<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\Resources;

use Spatie\LaravelData\Resource;
use Src\Application\Shared\Helpers\DateFormatHelper;
use Src\Domain\Process\Models\ManualRegistrationRequest;

class ManualRegistrationRequestResource extends Resource
{
    public function __construct(
        public string $id,
        public string $process_number,
        public string $reason,
        public string $reason_label,
        public string $status,
        public ?string $lawyer_role,
        public int $unassigned_actions_count,
        public string $requested_by_name,
        public ?string $requested_by_identification,
        public string $created_at,
    ) {}

    public static function fromModel(ManualRegistrationRequest $request): self
    {
        $request->loadMissing('appUser');

        $user = $request->appUser;
        if ($user === null) {
            $name = '—';
            $identification = null;
        } else {
            $name = trim($user->name.' '.$user->last_name) ?: '—';
            $identification = $user->identification;
        }

        return new self(
            id: $request->id,
            process_number: $request->process_number,
            reason: $request->reason->value,
            reason_label: $request->reason->label(),
            status: $request->status->value,
            lawyer_role: $request->lawyer_role?->value,
            unassigned_actions_count: $request->unassigned_actions_count,
            requested_by_name: $name,
            requested_by_identification: $identification,
            created_at: DateFormatHelper::formatDateTime($request->created_at),
        );
    }
}
