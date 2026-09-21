<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Raised by Redis clients on transport-level failures (connect, timeout,
 * error reply). The RedisHandler catches these and fails open, so the agent
 * keeps operating from memory.
 */
final class RedisException extends GuardAgentException
{
}
