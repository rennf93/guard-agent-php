<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Persistence;

use Redis;

/**
 * RedisClientInterface adapter over the ext-redis (phpredis) \Redis client.
 * ext-redis is an OPTIONAL extension: this adapter is only instantiated when
 * the host application constructs it with a connected client, so the package
 * itself never requires the extension. The type hint against \Redis only
 * resolves when this class is actually used.
 *
 * Pass an already-connected client (host, auth, and database live there):
 *
 *   $redis = new \Redis();
 *   $redis->connect('127.0.0.1', 6379);
 *   $agent->initializeRedis(new RedisHandler(new ExtRedisClient($redis), 'guard:agent', $logger));
 */
final class ExtRedisClient implements RedisClientInterface
{
    public function __construct(private readonly Redis $redis)
    {
    }

    public function ping(): void
    {
        $this->redis->ping();
    }

    public function get(string $key): ?string
    {
        $value = $this->redis->get($key);

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): bool
    {
        if ($ttlSeconds === null) {
            $reply = $this->redis->set($key, $value);
        } else {
            $reply = $this->redis->setex($key, $ttlSeconds, $value);
        }

        return $reply !== false;
    }

    public function delete(string ...$keys): int
    {
        if ($keys === []) {
            return 0;
        }

        return (int) $this->redis->del(...$keys);
    }

    /** @return list<string> */
    public function keys(string $pattern): array
    {
        $result = $this->redis->keys($pattern);

        return is_array($result) ? array_values(array_map('strval', $result)) : [];
    }

    public function close(): void
    {
        try {
            $this->redis->close();
        } catch (\Throwable) {
            // Best effort; phpredis throws on an already-closed socket.
        }
    }
}
