<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Base class for all guard-agent-php errors. Mirrors guard_agent/exceptions.py
 * plus the two utils-level exceptions (RateLimitedException,
 * SerializationException) via the sibling ports.
 *
 * Failure policy, mirrored from the Python agent and documented in the README:
 * nothing on the sendEvent/sendMetric/flushBuffer/tick event path ever throws
 * into the host application. The only typed errors callers can observe on
 * normal operation are BufferFullException (overflow policy "raise" through a
 * standalone EventBuffer) and ConfigException at construction time.
 */
class GuardAgentException extends \RuntimeException
{
}
