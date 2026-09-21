<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Raised on HTTP 429. Carries the server-supplied Retry-After in seconds
 * (default 60 when the header is absent or unparseable).
 */
final class RateLimitedException extends GuardAgentException
{
    public function __construct(
        public readonly float $retryAfterSeconds,
        ?string $message = null,
    ) {
        parent::__construct(
            $message ?? sprintf('Rate limited by server, retry after %.1fs', $retryAfterSeconds)
        );
    }
}
