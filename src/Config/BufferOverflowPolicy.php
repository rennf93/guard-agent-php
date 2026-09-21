<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Config;

/**
 * Overflow behavior when the in-memory buffer is full, mirroring the Python
 * agent's Literal["drop", "block", "raise"].
 *
 * - drop (default): evict the oldest entry.
 * - block: backpressure the caller until space frees. The single-threaded
 *   PHP caveat: space only frees when a drain runs, so the agent drains
 *   inline (respecting the per-kind backoff gates) while waiting. This is
 *   the one deliberate, opt-in exception to failure isolation.
 * - raise: throw BufferFullException (GuardAgent::sendEvent catches and
 *   logs it; a standalone EventBuffer surfaces it to its caller).
 */
enum BufferOverflowPolicy: string
{
    case Drop = 'drop';
    case Block = 'block';
    case Raise = 'raise';

    /** Parse a policy string, or null when the value is not a known policy. */
    public static function tryFromValue(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }
        if (is_string($value)) {
            return self::tryFrom(strtolower($value));
        }

        return null;
    }
}
