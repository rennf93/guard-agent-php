<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Model;

use RenzoFranceschini\GuardAgent\Exception\InvalidEventException;

/**
 * Performance metric, mirroring SecurityMetric (guard_agent/models.py:232-247).
 * metric_type is validated against KnownTypes::METRIC_TYPES; the value is a
 * required finite number.
 */
final class SecurityMetric
{
    /**
     * @param array<string, string> $tags
     */
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $metricType,
        public readonly int|float $value,
        public readonly ?string $endpoint = null,
        public readonly array $tags = [],
    ) {
    }

    /**
     * Normalize arbitrary input into a SecurityMetric.
     *
     * @throws InvalidEventException
     */
    public static function normalize(mixed $input): self
    {
        if ($input instanceof self) {
            return $input;
        }
        if (!is_array($input)) {
            throw new InvalidEventException('Metric must be an array or SecurityMetric');
        }

        $rawType = self::pick($input, 'metricType', 'metric_type');
        if (!is_string($rawType) || !in_array($rawType, KnownTypes::METRIC_TYPES, true)) {
            throw new InvalidEventException(
                'metric_type must be one of: ' . implode(', ', KnownTypes::METRIC_TYPES)
            );
        }

        $rawValue = self::pick($input, 'value', 'value');
        if ($rawValue === null || is_bool($rawValue)) {
            throw new InvalidEventException('value is required and must be a number');
        }
        if (!is_int($rawValue) && !is_float($rawValue)) {
            if (is_string($rawValue) && is_numeric(trim($rawValue))) {
                $rawValue = (float) trim($rawValue);
            } else {
                throw new InvalidEventException('value is required and must be a number');
            }
        }
        if ((is_float($rawValue) && (is_nan($rawValue) || is_infinite($rawValue)))) {
            throw new InvalidEventException('value is required and must be a number');
        }

        return new self(
            timestamp: WireFormat::parseTimestamp(self::pick($input, 'timestamp', 'timestamp'), 'timestamp'),
            metricType: $rawType,
            value: $rawValue,
            endpoint: self::optionalString(self::pick($input, 'endpoint', 'endpoint'), 'endpoint'),
            tags: self::tagsArray(self::pick($input, 'tags', 'tags')),
        );
    }

    /**
     * Copy with replaced tags (used for redaction).
     *
     * @param array<string, string> $tags
     */
    public function withTags(array $tags): self
    {
        return new self(
            timestamp: $this->timestamp,
            metricType: $this->metricType,
            value: $this->value,
            endpoint: $this->endpoint,
            tags: $tags,
        );
    }

    /**
     * Wire rendering (snake_case, mirroring model_dump()).
     *
     * @return array<string, mixed>
     */
    public function toWire(): array
    {
        return [
            'timestamp' => WireFormat::isoUtc($this->timestamp),
            'metric_type' => $this->metricType,
            'value' => $this->value,
            'endpoint' => $this->endpoint,
            'tags' => (object) $this->tags,
        ];
    }

    /**
     * @param array<string, mixed> $source
     */
    private static function pick(array $source, string $camel, string $snake): mixed
    {
        if (array_key_exists($camel, $source)) {
            return $source[$camel];
        }

        return $source[$snake] ?? null;
    }

    /**
     * @throws InvalidEventException
     */
    private static function optionalString(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new InvalidEventException("{$field} must be a string or null");
        }

        return $value;
    }

    /**
     * @return array<string, string>
     *
     * @throws InvalidEventException
     */
    private static function tagsArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new InvalidEventException('tags must be an array');
        }
        $tags = [];
        foreach ($value as $key => $item) {
            $tags[(string) $key] = is_string($item) ? $item : (string) $item;
        }

        return $tags;
    }
}
