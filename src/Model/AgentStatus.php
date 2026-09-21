<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Model;

/**
 * Agent health snapshot, mirroring AgentStatus (guard_agent/models.py:327-337).
 * status is 'healthy', 'degraded', or 'failed'.
 */
final class AgentStatus
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $status,
        public readonly float $uptime,
        public readonly int $eventsSent,
        public readonly int $eventsFailed,
        public readonly int $bufferSize,
        public readonly ?\DateTimeImmutable $lastFlush,
        public readonly array $errors,
    ) {
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
            'status' => $this->status,
            'uptime' => $this->uptime,
            'events_sent' => $this->eventsSent,
            'events_failed' => $this->eventsFailed,
            'buffer_size' => $this->bufferSize,
            'last_flush' => $this->lastFlush === null ? null : WireFormat::isoUtc($this->lastFlush),
            'errors' => $this->errors,
        ];
    }
}
