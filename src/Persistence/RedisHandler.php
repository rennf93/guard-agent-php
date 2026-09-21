<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Persistence;

use RenzoFranceschini\GuardAgent\Exception\RedisException;
use RenzoFranceschini\GuardAgent\Log\AgentLogger;
use RenzoFranceschini\GuardAgent\Log\DefaultAgentLogger;

/**
 * Namespaced durable store, mirroring guard_agent/protocols.py
 * RedisHandlerProtocol (lines 6-46) and the Go port's persistence type.
 *
 * Keys are `{keyPrefix}:{namespace}:{key}`; the buffer passes short keys and
 * the `agent_events` / `agent_metrics` namespaces.
 *
 * Fail-open policy (mirroring the Go port): writes are marked unavailable
 * after 3 consecutive failures for a 30s cooldown, so an unhealthy Redis
 * cannot tax the host request path; the TTL is the ultimate backstop.
 */
final class RedisHandler
{
    private const MAX_CONSECUTIVE_FAILURES = 3;

    private const COOLDOWN_SECONDS = 30.0;

    private int $consecutiveFailures = 0;

    private float $cooldownUntil = 0.0;

    private int $failureCount = 0;

    private readonly AgentLogger $logger;

    public function __construct(
        private readonly RedisClientInterface $client,
        private readonly string $keyPrefix = 'guard:agent',
        ?AgentLogger $logger = null,
    ) {
        $this->logger = $logger ?? new DefaultAgentLogger();
    }

    /** Full key for a namespaced record. */
    public function fullKey(string $namespace, string $key): string
    {
        return $this->keyPrefix . ':' . $namespace . ':' . $key;
    }

    /** Number of failed writes observed so far (reported in buffer stats). */
    public function failureCount(): int
    {
        return $this->failureCount;
    }

    public function getKey(string $namespace, string $key): ?string
    {
        return $this->client->get($this->fullKey($namespace, $key));
    }

    /**
     * Persist a record. Returns true only when it is durable. Failures are
     * fail-open: logged, counted, and subject to the write cooldown.
     */
    public function setKey(string $namespace, string $key, string $value, ?int $ttlSeconds = null): bool
    {
        if (!$this->writesAvailable()) {
            $this->failureCount++;

            return false;
        }
        try {
            $stored = $this->client->set($this->fullKey($namespace, $key), $value, $ttlSeconds);
        } catch (\Throwable $error) {
            $this->recordFailure($error, $key);

            return false;
        }
        if (!$stored) {
            $this->recordFailure(new RedisException('SET did not return OK'), $key);

            return false;
        }
        $this->consecutiveFailures = 0;

        return true;
    }

    /** Delete a confirmed record; best effort. */
    public function delete(string $namespace, string $key): int
    {
        try {
            return $this->client->delete($this->fullKey($namespace, $key));
        } catch (\Throwable $error) {
            $this->logger->warning("redis confirm (delete) failed for {$key}: " . $error->getMessage());

            return 0;
        }
    }

    /**
     * List full keys matching a namespace-relative pattern, e.g.
     * keys('agent_events:*').
     *
     * @return list<string>
     */
    public function keys(string $pattern): array
    {
        try {
            return $this->client->keys($this->keyPrefix . ':' . $pattern);
        } catch (\Throwable $error) {
            $this->logger->warning('redis keys failed: ' . $error->getMessage());

            return [];
        }
    }

    public function ping(): void
    {
        $this->client->ping();
    }

    public function close(): void
    {
        try {
            $this->client->close();
        } catch (\Throwable $error) {
            $this->logger->warning('redis.close failed: ' . $error->getMessage());
        }
    }

    private function writesAvailable(): bool
    {
        return microtime(true) >= $this->cooldownUntil;
    }

    private function recordFailure(\Throwable $error, string $key): void
    {
        $this->failureCount++;
        $this->consecutiveFailures++;
        if ($this->consecutiveFailures >= self::MAX_CONSECUTIVE_FAILURES) {
            $this->cooldownUntil = microtime(true) + self::COOLDOWN_SECONDS;
            $this->consecutiveFailures = 0;
            $this->logger->warning(
                sprintf(
                    'redis persistence failing (%s); pausing persist writes for %ss',
                    $error->getMessage(),
                    self::COOLDOWN_SECONDS
                )
            );

            return;
        }
        $this->logger->warning("redis persist failed for {$key}: " . $error->getMessage());
    }
}
