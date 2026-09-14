<?php

declare(strict_types=1);

namespace Src\Application\Shared\Services\Notification\Channels;

use Src\Domain\Process\Models\ManualRegistrationRequest;

/**
 * Discord alerts for manual process registration requests (ops / Edwin).
 */
readonly class ManualRegistrationDiscordNotificationService
{
    public function __construct(
        private DiscordNotificationChannelService $discordChannel,
    ) {}

    public function notify(ManualRegistrationRequest $request): bool
    {
        if (! $this->discordChannel->canSend(DiscordNotificationChannelService::CHANNEL_MANUAL_REGISTRATION)) {
            return false;
        }

        $request->loadMissing(['organization', 'appUser']);

        $user = $request->appUser;
        $org = $request->organization;

        $userName = $user === null
            ? '—'
            : (trim($user->name.' '.$user->last_name) ?: '—');

        $identification = $user === null ? '—' : ($user->identification ?: '—');
        $email = $user === null ? '—' : ($user->email ?: '—');
        $orgName = $org === null ? '—' : ($org->name ?: '—');
        $roleLabel = $request->lawyer_role?->getLabel() ?? '—';
        $unassigned = $request->unassigned_actions_count;

        $historicoLine = $unassigned > 0
            ? "Ya hay **{$unassigned}** actuación(es) en histórico pendiente (`unassigned_process_actions`); se asociarán al crear el proceso."
            : 'Sin histórico pendiente en `unassigned_process_actions`.';

        $embed = [
            'title' => 'Solicitud de alta manual',
            'description' => $request->reason->label()."\n\n".$historicoLine,
            'color' => '#E67E22',
            'fields' => [
                [
                    'name' => 'Radicado',
                    'value' => '`'.$request->process_number.'`',
                    'inline' => false,
                ],
                [
                    'name' => 'Organización',
                    'value' => $orgName,
                    'inline' => true,
                ],
                [
                    'name' => 'Motivo',
                    'value' => $request->reason->value,
                    'inline' => true,
                ],
                [
                    'name' => 'Usuario',
                    'value' => $userName,
                    'inline' => true,
                ],
                [
                    'name' => 'Cédula',
                    'value' => (string) $identification,
                    'inline' => true,
                ],
                [
                    'name' => 'Email',
                    'value' => $email,
                    'inline' => true,
                ],
                [
                    'name' => 'Rol abogado',
                    'value' => $roleLabel,
                    'inline' => true,
                ],
                [
                    'name' => 'Request ID',
                    'value' => '`'.$request->id.'`',
                    'inline' => false,
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $this->discordChannel->send(
            DiscordNotificationChannelService::CHANNEL_MANUAL_REGISTRATION,
            '**Alta manual** — radicado `'.$request->process_number.'` pendiente de digitación / publicaciones procesales.',
            [$embed]
        );

        return true;
    }
}
