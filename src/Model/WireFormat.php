<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Model;

use RenzoFranceschini\GuardAgent\Exception\InvalidEventException;

/**
 * Timestamp and wire-format helpers shared by the telemetry models.
 *
 * Timestamps are accepted as DateTimeInterface instances, epoch seconds
 * (int/float; PHP's time() convention, unlike the TypeScript port which reads
 * epoch milliseconds), or parseable strings (ISO-8601 and friends). The wire
 * rendering is ISO-8601 UTC with millisecond precision, matching the
 * TypeScript port's toISOString() output; the server's pydantic parser
 * accepts both that and the Python agent's datetime str().
 */
final class WireFormat
{
    private function __construct()
    {
    }

    public static function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    /**
     * @throws InvalidEventException
     */
    public static function parseTimestamp(mixed $value, string $field): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        if (is_int($value) || is_float($value)) {
            try {
                return new \DateTimeImmutable('@' . (string) (int) $value);
            } catch (\Exception) {
                throw new InvalidEventException("{$field} is not a valid epoch timestamp");
            }
        }
        if (is_string($value) && trim($value) !== '') {
            try {
                return new \DateTimeImmutable($value);
            } catch (\Exception) {
                throw new InvalidEventException("{$field} is not a valid ISO-8601 timestamp");
            }
        }

        throw new InvalidEventException("{$field} is required (DateTimeInterface, epoch seconds or ISO string)");
    }

    public static function timestampOrDefault(mixed $value): \DateTimeImmutable
    {
        if ($value === null) {
            return self::now();
        }

        return self::parseTimestamp($value, 'timestamp');
    }

    /** ISO-8601 UTC rendering used on the wire (mirrors toISOString()). */
    public static function isoUtc(\DateTimeImmutable $value): string
    {
        return $value->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s.v\\Z');
    }
}
