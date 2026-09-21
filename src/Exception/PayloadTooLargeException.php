<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Raised on HTTP 413 (decompressed body above the 262144-byte cap). The
 * caller splits the batch or drops a singleton. Like every permanent client
 * error it is exempt from the circuit breaker.
 */
final class PayloadTooLargeException extends PermanentClientException
{
    public function __construct(string $detail = '')
    {
        parent::__construct(413, $detail);
    }
}
