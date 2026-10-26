<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Pusher\ApiErrorException;
use Throwable;

class ReverbPresenceChannelRoster implements PresenceChannelRoster
{
    public function memberIds(): ?array
    {
        $appId = config('broadcasting.connections.reverb.app_id');

        if (! is_string($appId) || $appId === '') {
            return null;
        }

        try {
            $broadcaster = Broadcast::connection('reverb');

            if (! $broadcaster instanceof PusherBroadcaster) {
                return null;
            }

            $channel = 'presence-'.config('presence.channel', 'app-users.online');
            $response = $broadcaster->getPusher()->get('/channels/'.rawurlencode($channel).'/users');
        } catch (ApiErrorException $exception) {
            if ($exception->getCode() === 404) {
                return [];
            }

            Log::warning('No se pudo leer la presencia de Reverb.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        } catch (Throwable $exception) {
            Log::warning('No se pudo leer la presencia de Reverb.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        return $this->idsFromResponse($response);
    }

    /**
     * @return list<string>|null
     */
    private function idsFromResponse(mixed $response): ?array
    {
        if (is_string($response)) {
            $decoded = json_decode($response, true);
            $response = is_array($decoded) ? $decoded : null;
        }

        if (is_object($response)) {
            $decoded = json_decode(json_encode($response), true);
            $response = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($response)) {
            return null;
        }

        $users = $response['users'] ?? null;

        if (! is_array($users)) {
            return [];
        }

        $ids = [];

        foreach ($users as $user) {
            if (! is_array($user)) {
                continue;
            }

            if (! isset($user['id'])) {
                continue;
            }

            if (! is_scalar($user['id'])) {
                continue;
            }

            $ids[] = (string) $user['id'];
        }

        return $ids;
    }
}
