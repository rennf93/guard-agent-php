<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Exception;

/**
 * Raised on a non-retryable 4xx (400/404/422). The batch must be dropped,
 * not retried. 401/403 intentionally do NOT use this class: the Python agent
 * classifies auth failures as generic errors, which retry until attempts are
 * exhausted and then requeue; this port mirrors that. These errors are exempt
 * from the circuit breaker: they are batch-level rejections, not transport
 * health signals.
 */
class PermanentClientException extends GuardAgentException
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $detail = '',
    ) {
        $message = "Permanent client error {$statusCode}";
        if ($detail !== '') {
            $message .= ": {$detail}";
        }
        parent::__construct($message);
    }
}
