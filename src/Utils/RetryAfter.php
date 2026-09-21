<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

/**
 * RFC 7231 Retry-After parsing, mirroring parse_retry_after_seconds
 * (guard_agent/utils.py:28-36): a missing, unparseable, or non-numeric header
 * falls back to the default (60s); negative values clamp to 0. The transport
 * caps the honored delay at 300s.
 */
final class RetryAfter
{
    private function __construct()
    {
    }

    public static function parse(?string $headerValue, float $default = 60.0): float
    {
        if ($headerValue === null || trim($headerValue) === '') {
            return $default;
        }
        if (!is_numeric(trim($headerValue))) {
            return $default;
        }
        $value = (float) trim($headerValue);

        return max(0.0, $value);
    }
}
