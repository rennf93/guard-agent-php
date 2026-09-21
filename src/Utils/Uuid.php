<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

/**
 * UUID v4 generation without ext-uuid (random_bytes based), used for
 * idempotency keys, batch IDs, persistence keys, and install IDs.
 */
final class Uuid
{
    private function __construct()
    {
    }

    /** Return a random 32-hex-character string (not a canonical UUID). */
    public static function hex(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** Return a canonical lowercase UUID v4 string. */
    public static function v4(): string
    {
        $bytes = random_bytes(16);
        // Set the version (4) and variant (10xx) bits per RFC 4122.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }

    /** Test whether a string is a canonical UUID (any version). */
    public static function isUuid(string $value): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }
}
