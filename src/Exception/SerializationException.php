<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Raised when a value cannot be serialized to JSON for transport. The
 * transport aborts the POST and retains the batch (mirroring
 * safe_json_serialize in guard_agent/utils.py:260-266).
 */
final class SerializationException extends GuardAgentException
{
}
