<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Transport;

/**
 * Local send rate limiter, mirroring RateLimiter (guard_agent/utils.py:309-337).
 * The transport defaults to 100 calls per 60s window.
 */
final class RateLimiter
{
    /** @var list<float> */
    private array $calls = [];

    public function __construct(
        private readonly int $maxCalls = 100,
        private readonly float $timeWindow = 60.0,
    ) {
    }

    public function acquire(): bool
    {
        $now = microtime(true);
        $this->calls = array_values(array_filter($this->calls, fn (float $callTime): bool => $now - $callTime < $this->timeWindow));
        if (count($this->calls) < $this->maxCalls) {
            $this->calls[] = $now;

            return true;
        }

        return false;
    }

    public function getRetryAfter(): float
    {
        if ($this->calls === []) {
            return 0.0;
        }
        $oldestCall = min($this->calls);

        return max(0.0, $this->timeWindow - (microtime(true) - $oldestCall));
    }
}
