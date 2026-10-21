<?php

declare(strict_types=1);

namespace Src\Application\Admin\Presence;

interface PresenceChannelRoster
{
    /**
     * Member ids currently subscribed to the presence channel.
     * Null when Reverb cannot be reached.
     *
     * @return list<string>|null
     */
    public function memberIds(): ?array;
}
