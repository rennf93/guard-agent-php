<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Model;

use RenzoFranceschini\GuardAgent\Exception\InvalidEventException;
use RenzoFranceschini\GuardAgent\Utils\Uuid;

/**
 * Security event, mirroring SecurityEvent (guard_agent/models.py:208-229).
 *
 * The ingestion API defines its request schema with the Python agent's
 * pydantic models directly (guard-core-app telemetry_models.py imports
 * guard_agent.models), so the wire format is snake_case with ISO-8601
 * timestamps and UUID idempotency keys. This class keeps camelCase
 * properties (idiomatic PHP) and converts at the transport boundary.
 *
 * normalize() reads only the known fields and accepts BOTH camelCase and
 * snake_case input keys, so adapter code that already speaks the Python field
 * names works unchanged. Missing or invalid values raise
 * InvalidEventException, which GuardAgent::sendEvent() catches and logs.
 */
final class SecurityEvent
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $idempotencyKey,
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $eventType,
        public readonly string $ipAddress = '',
        public readonly ?string $country = null,
        public readonly ?string $userAgent = null,
        public readonly string $actionTaken = '',
        public readonly string $reason = '',
        public readonly ?string $endpoint = null,
        public readonly ?string $method = null,
        public readonly ?int $statusCode = null,
        public readonly int|float|null $responseTime = null,
        public readonly ?string $decoratorType = null,
        public readonly ?string $ruleType = null,
        public readonly ?string $patternMatched = null,
        public readonly ?string $handlerName = null,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Normalize arbitrary input into a SecurityEvent, mirroring
     * IngestMixin._normalize_event (guard_agent/_client_ingest.py:49-54).
     *
     * @throws InvalidEventException
     */
    public static function normalize(mixed $input): self
    {
        if ($input instanceof self) {
            return $input;
        }
        if (!is_array($input)) {
            throw new InvalidEventException('Event must be an array or SecurityEvent');
        }

        $rawKey = self::pick($input, 'idempotencyKey', 'idempotency_key');
        if ($rawKey === null) {
            $idempotencyKey = Uuid::v4();
        } elseif (is_string($rawKey) && Uuid::isUuid($rawKey)) {
            $idempotencyKey = $rawKey;
        } else {
            throw new InvalidEventException('idempotency_key must be a UUID string');
        }

        $rawIp = self::pick($input, 'ipAddress', 'ip_address');
        $ipAddress = $rawIp === null
            ? ''
            : self::requiredString($rawIp, 'ip_address');

        $rawAction = self::pick($input, 'actionTaken', 'action_taken');
        $rawReason = self::pick($input, 'reason', 'reason');

        return new self(
            idempotencyKey: $idempotencyKey,
            timestamp: WireFormat::parseTimestamp(self::pick($input, 'timestamp', 'timestamp'), 'timestamp'),
            eventType: self::requiredString(self::pick($input, 'eventType', 'event_type'), 'event_type'),
            ipAddress: $ipAddress,
            country: self::optionalString(self::pick($input, 'country', 'country'), 'country'),
            userAgent: self::optionalString(self::pick($input, 'userAgent', 'user_agent'), 'user_agent'),
            actionTaken: $rawAction === null ? '' : self::requiredString($rawAction, 'action_taken'),
            reason: $rawReason === null ? '' : self::requiredString($rawReason, 'reason'),
            endpoint: self::optionalString(self::pick($input, 'endpoint', 'endpoint'), 'endpoint'),
            method: self::optionalString(self::pick($input, 'method', 'method'), 'method'),
            statusCode: self::optionalInt(self::pick($input, 'statusCode', 'status_code'), 'status_code'),
            responseTime: self::optionalNumber(self::pick($input, 'responseTime', 'response_time'), 'response_time'),
            decoratorType: self::optionalString(self::pick($input, 'decoratorType', 'decorator_type'), 'decorator_type'),
            ruleType: self::optionalString(self::pick($input, 'ruleType', 'rule_type'), 'rule_type'),
            patternMatched: self::optionalString(self::pick($input, 'patternMatched', 'pattern_matched'), 'pattern_matched'),
            handlerName: self::optionalString(self::pick($input, 'handlerName', 'handler_name'), 'handler_name'),
            metadata: self::metadataArray(self::pick($input, 'metadata', 'metadata')),
        );
    }

    /**
     * Copy with a replaced metadata bag (used for redaction).
     *
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            idempotencyKey: $this->idempotencyKey,
            timestamp: $this->timestamp,
            eventType: $this->eventType,
            ipAddress: $this->ipAddress,
            country: $this->country,
            userAgent: $this->userAgent,
            actionTaken: $this->actionTaken,
            reason: $this->reason,
            endpoint: $this->endpoint,
            method: $this->method,
            statusCode: $this->statusCode,
            responseTime: $this->responseTime,
            decoratorType: $this->decoratorType,
            ruleType: $this->ruleType,
            patternMatched: $this->patternMatched,
            handlerName: $this->handlerName,
            metadata: $metadata,
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
            'idempotency_key' => $this->idempotencyKey,
            'timestamp' => WireFormat::isoUtc($this->timestamp),
            'event_type' => $this->eventType,
            'ip_address' => $this->ipAddress,
            'country' => $this->country,
            'user_agent' => $this->userAgent,
            'action_taken' => $this->actionTaken,
            'reason' => $this->reason,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'status_code' => $this->statusCode,
            'response_time' => $this->responseTime,
            'decorator_type' => $this->decoratorType,
            'rule_type' => $this->ruleType,
            'pattern_matched' => $this->patternMatched,
            'handler_name' => $this->handlerName,
            'metadata' => (object) $this->metadata,
        ];
    }

    /**
     * Read a field accepting both camelCase and snake_case keys.
     *
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
    private static function requiredString(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new InvalidEventException("{$field} is required and must be a string");
        }

        return $value;
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
     * @throws InvalidEventException
     */
    private static function optionalInt(mixed $value, string $field): ?int
    {
        $parsed = self::optionalNumber($value, $field);
        if ($parsed === null) {
            return null;
        }
        if (is_float($parsed) && floor($parsed) !== $parsed) {
            throw new InvalidEventException("{$field} must be an integer or null");
        }

        return (int) $parsed;
    }

    /**
     * @throws InvalidEventException
     */
    private static function optionalNumber(mixed $value, string $field): int|float|null
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            if (is_float($value) && (is_nan($value) || is_infinite($value))) {
                throw new InvalidEventException("{$field} must be a finite number or null");
            }

            return $value;
        }
        if (is_string($value) && trim($value) !== '' && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        throw new InvalidEventException("{$field} must be a finite number or null");
    }

    /**
     * @return array<string, mixed>
     *
     * @throws InvalidEventException
     */
    private static function metadataArray(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new InvalidEventException('metadata must be an array');
        }

        return $value;
    }
}
