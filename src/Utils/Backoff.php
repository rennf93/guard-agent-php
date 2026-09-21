<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

/**
 * Exponential backoff, mirroring calculate_backoff_delay
 * (guard_agent/utils.py:248-253): base * 2^attempt capped at maxDelay.
 *
 * The transport uses base = config.backoffFactor and max = 60s between retry
 * attempts; the agent uses base = config.flushInterval and max = 300s for the
 * per-kind failure-streak gates.
 */
final class Backoff
{
    private function __construct()
    {
    }

    public static function calculate(int $attempt, float $baseDelay = 1.0, float $maxDelay = 60.0): float
    {
        $safeAttempt = max(0, $attempt);

        return min($baseDelay * (2 ** $safeAttempt), $maxDelay);
    }
}
