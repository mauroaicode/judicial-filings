<?php

declare(strict_types=1);

use Src\Application\Admin\Presence\ReverbPresenceChannelRoster;

it('reads member ids when Reverb returns an object payload', function (): void {
    $roster = new ReverbPresenceChannelRoster;
    $method = new ReflectionMethod($roster, 'idsFromResponse');

    $payload = json_decode('{"users":[{"id":"app-user-id"},{"id":"admin-id"}]}');

    expect($method->invoke($roster, $payload))->toBe(['app-user-id', 'admin-id']);
});
