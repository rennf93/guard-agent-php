<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

/**
 * Redact sensitive headers from telemetry metadata/tags, mirroring
 * sanitize_headers (guard_agent/utils.py:62-198). Recurses into nested arrays
 * and JSON-looking strings up to a bounded depth; anything deeper or
 * unclassifiable is redacted wholesale rather than raising. Keys match
 * case-insensitively after trimming.
 */
final class HeadersRedactor
{
    public const REDACTED = '[REDACTED]';

    private const MAX_SANITIZE_DEPTH = 10;

    private const MAX_JSON_SCAN_LEN = 8192;

    private function __construct()
    {
    }

    /**
     * @param list<string> $sensitiveHeaders
     */
    public static function sanitize(mixed $value, array $sensitiveHeaders): mixed
    {
        $lowered = [];
        foreach ($sensitiveHeaders as $header) {
            $lowered[strtolower(trim((string) $header))] = true;
        }

        return self::sanitizeValue($value, $lowered, 0);
    }

    /**
     * @param array<string, true> $lowered
     */
    private static function sanitizeValue(mixed $value, array $lowered, int $depth): mixed
    {
        try {
            return self::sanitizeValueUnsafe($value, $lowered, $depth);
        } catch (\Throwable) {
            return self::REDACTED;
        }
    }

    /**
     * @param array<string, true> $lowered
     */
    private static function sanitizeValueUnsafe(mixed $value, array $lowered, int $depth): mixed
    {
        if ($depth > self::MAX_SANITIZE_DEPTH) {
            return self::REDACTED;
        }
        if (is_string($value)) {
            return self::sanitizeString($value, $lowered, $depth);
        }
        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                if (is_string($key) && isset($lowered[strtolower(trim($key))])) {
                    $result[$key] = self::REDACTED;
                } else {
                    $result[$key] = self::sanitizeValue($item, $lowered, $depth + 1);
                }
            }

            return $result;
        }
        if (is_object($value)) {
            if ($value instanceof \DateTimeInterface) {
                return $value;
            }

            return self::REDACTED;
        }

        return self::REDACTED;
    }

    /**
     * @param array<string, true> $lowered
     */
    private static function sanitizeString(string $value, array $lowered, int $depth): string
    {
        $stripped = trim($value);
        if ($stripped === '') {
            return $value;
        }
        $first = $stripped[0];
        if ($first !== '{' && $first !== '[' && $first !== '"') {
            return $value;
        }
        if (strlen($stripped) > self::MAX_JSON_SCAN_LEN) {
            return self::REDACTED;
        }
        try {
            $parsed = json_decode($stripped, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $value;
        }
        $sanitized = self::sanitizeValue($parsed, $lowered, $depth + 1);
        try {
            return Json::encode($sanitized);
        } catch (\Throwable) {
            return $value;
        }
    }
}
