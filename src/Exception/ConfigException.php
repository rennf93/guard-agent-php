<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Thrown when AgentConfig fails validation. Raises at construction time,
 * mirroring GuardAgentHandler.__init__ raising ValueError
 * (guard_agent/client.py:72-74).
 */
final class ConfigException extends GuardAgentException
{
}
