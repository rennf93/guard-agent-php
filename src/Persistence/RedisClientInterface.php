<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Persistence;

use RenzoFranceschini\GuardAgent\Exception\RedisException;

/**
 * Minimal async-free key-value store the agent uses for durable buffering,
 * mirroring RedisHandlerProtocol (guard_agent/protocols.py:6-46). Reads return
 * null on a miss (never throw for absent keys); TTLs are in seconds; anything
 * that is not a clean reply throws RedisException so the caller can fail open.
 */
interface RedisClientInterface
{
    /** PING the server (and connect if needed). Throws on failure. */
    public function ping(): void;

    /** GET; null on miss. */
    public function get(string $key): ?string;

    /** SET with an optional EX TTL in seconds; true when durable. */
    public function set(string $key, string $value, ?int $ttlSeconds = null): bool;

    /** DEL one or more keys; returns the number deleted. */
    public function delete(string ...$keys): int;

    /** KEYS pattern; returns matching key names (may be empty). */
    public function keys(string $pattern): array;

    /** Release the connection. Must be safe to call twice. */
    public function close(): void;
}
