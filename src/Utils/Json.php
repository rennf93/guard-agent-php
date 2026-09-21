<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

use RenzoFranceschini\GuardAgent\Exception\SerializationException;

/**
 * JSON helpers, mirroring safe_json_serialize / safe_json_deserialize
 * (guard_agent/utils.py:260-278). Encoding produces Python-json.dumps
 * compatible bytes: forward slashes are left unescaped (JSON_UNESCAPED_SLASHES)
 * while non-ASCII characters stay \\uXXXX-escaped, matching Python's
 * ensure_ascii default. Every helper is total: decoding failures return null
 * instead of raising, and encoding failures raise the typed
 * SerializationException the transport retains the batch on.
 */
final class Json
{
    private function __construct()
    {
    }

    /**
     * Serialize to compact JSON, raising SerializationException on failure.
     *
     * @throws SerializationException
     */
    public static function encode(mixed $value): string
    {
        try {
            $encoded = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $exception) {
            throw new SerializationException($exception->getMessage(), previous: $exception);
        }
        if ($encoded === false) {
            throw new SerializationException('Value is not JSON-serializable');
        }

        return $encoded;
    }

    /**
     * Parse a JSON object, returning null on any failure or non-array result
     * (mirrors safe_json_deserialize used for persisted buffer records).
     */
    public static function decodeAssocOrNull(string $text): ?array
    {
        try {
            $parsed = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }

    /**
     * Parse any JSON document. Returns null only when the text is not valid
     * JSON (JSON_THROW_ON_ERROR + catch disambiguates a literal `null` body).
     */
    public static function decodeAnyOrNull(string $text): mixed
    {
        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
    }
}
