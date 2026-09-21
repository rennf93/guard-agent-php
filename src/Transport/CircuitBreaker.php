<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Transport;

use RenzoFranceschini\GuardAgent\Exception\GuardAgentException;
use RenzoFranceschini\GuardAgent\Exception\PermanentClientException;

/**
 * Transport circuit breaker, mirroring CircuitBreaker
 * (guard_agent/utils.py:340-382). Defaults: 5 consecutive failures open the
 * circuit for 60s. PermanentClientException (400/404/413/422) is re-thrown
 * without counting as a failure, because those are batch-level rejections,
 * not transport health signals.
 */
final class CircuitBreaker
{
    public const CLOSED = 'CLOSED';
    public const OPEN = 'OPEN';
    public const HALF_OPEN = 'HALF_OPEN';

    public int $failureCount = 0;

    public ?float $lastFailureTime = null;

    public string $state = self::CLOSED;

    public function __construct(
        public readonly int $failureThreshold = 5,
        public readonly float $recoveryTimeout = 60.0,
    ) {
    }

    public function isOpen(): bool
    {
        return $this->state === self::OPEN;
    }

    /** Invoke $fn under breaker protection. */
    public function call(callable $fn): mixed
    {
        if ($this->state === self::OPEN) {
            if ($this->lastFailureTime !== null && microtime(true) - $this->lastFailureTime > $this->recoveryTimeout) {
                $this->state = self::HALF_OPEN;
            } else {
                throw new GuardAgentException('Circuit breaker is OPEN');
            }
        }

        try {
            $result = $fn();
        } catch (\Throwable $error) {
            if ($error instanceof PermanentClientException) {
                throw $error;
            }
            $this->onFailure();
            throw $error;
        }
        $this->onSuccess();

        return $result;
    }

    private function onSuccess(): void
    {
        $this->failureCount = 0;
        $this->state = self::CLOSED;
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = microtime(true);
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::OPEN;
        }
    }
}
