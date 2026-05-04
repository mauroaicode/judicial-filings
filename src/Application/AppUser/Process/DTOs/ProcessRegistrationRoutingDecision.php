<?php

declare(strict_types=1);

namespace Src\Application\AppUser\Process\DTOs;

readonly class ProcessRegistrationRoutingDecision
{
    /**
     * @param  array<int, array<string, mixed>>|null  $prefetchedApiProcesses  Lista `procesos[]` del Portal (evita un segundo fetchProcesses en modo inline).
     */
    public function __construct(
        public bool $deferToQueue,
        public ?array $prefetchedApiProcesses = null,
    ) {}
}
