<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Transport;

use RenzoFranceschini\GuardAgent\Model\AgentStatus;
use RenzoFranceschini\GuardAgent\Model\SecurityEvent;
use RenzoFranceschini\GuardAgent\Model\SecurityMetric;

/**
 * Transport seam, mirroring TransportProtocol (guard_agent/protocols.py).
 * HttpTransport is the real implementation; tests and hosts may inject a
 * fake. Every implementation must honor the agent's failure-isolation
 * contract for the return values: sendEvents/sendMetrics/sendStatus return
 * false on transient failure (the caller requeues) and true on acceptance or
 * intentional drop; they may throw typed transport errors, which the agent
 * converts into requeue + backoff.
 */
interface TransportInterface
{
    public function initialize(): void;

    /**
     * @param list<SecurityEvent> $events
     */
    public function sendEvents(array $events): bool;

    /**
     * @param list<SecurityMetric> $metrics
     */
    public function sendMetrics(array $metrics): bool;

    public function sendStatus(AgentStatus $status): bool;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array;

    public function close(): void;
}
