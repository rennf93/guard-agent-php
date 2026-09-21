<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

/**
 * Collapse an HTTP response body into a bounded single-line summary,
 * mirroring summarize_response_body (guard_agent/utils.py:207-218). Length is
 * measured in bytes (no mbstring dependency); the truncated tail is only ever
 * used inside log lines.
 */
final class ResponseSummary
{
    private function __construct()
    {
    }

    public static function summarize(string $text, int $maxLength = 300): string
    {
        $parts = preg_split('/\s+/', $text) ?: [];
        $collapsed = implode(' ', array_values(array_filter($parts, static fn (string $part): bool => $part !== '')));
        if (strlen($collapsed) <= $maxLength) {
            return $collapsed;
        }

        return sprintf(
            '%s... [truncated, %d chars total]',
            substr($collapsed, 0, $maxLength),
            strlen($text)
        );
    }
}
