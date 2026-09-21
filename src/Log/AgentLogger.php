<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Log;

/**
 * Logger seam. The Python guard-agent uses the stdlib `logging` module; this
 * port accepts an injected logger instead so host applications keep control
 * of where telemetry warnings land. Implementations must never throw.
 */
interface AgentLogger
{
    public function debug(string $message): void;

    public function info(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;
}
