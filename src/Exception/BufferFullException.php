<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Thrown when an EventBuffer is full and the configured overflow policy is
 * "raise". Under the default "drop" policy and the "block" policy this is
 * never thrown. GuardAgent::sendEvent() catches and logs it, so it never
 * reaches the host application through the agent.
 */
final class BufferFullException extends GuardAgentException
{
}
