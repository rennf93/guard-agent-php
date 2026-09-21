<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Persistence;

use RenzoFranceschini\GuardAgent\Exception\RedisException;

/**
 * RedisClientInterface adapter over a predis/predis client (the pure-PHP
 * Redis client). predis is an OPTIONAL dependency: this adapter is only
 * instantiated when the host application constructs it with an installed
 * client, so the package itself never requires it.
 *
 * Pass an already-configured \Predis\Client (options, scheme, auth, and
 * database all live there):
 *
 *   $agent->initializeRedis(new RedisHandler(
 *       new PredisRedisClient(new \Predis\Client('redis://127.0.0.1:6379')),
 *       'guard:agent',
 *       $logger,
 *   ));
 */
final class PredisRedisClient implements RedisClientInterface
{
    private readonly object $client;

    /**
     * @param object $predis an installed \Predis\ClientInterface instance
     *
     * @throws RedisException when the instance does not speak the Predis surface
     */
    public function __construct(object $predis)
    {
        foreach (['get', 'set', 'del', 'keys', 'ping'] as $method) {
            if (!method_exists($predis, $method)) {
                throw new RedisException(
                    'PredisRedisClient requires a Predis client instance; missing method ' . $method
                );
            }
        }
        $this->client = $predis;
    }

    public function ping(): void
    {
        $this->client->ping();
    }

    public function get(string $key): ?string
    {
        $value = $this->client->get($key);

        return is_string($value) ? $value : null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): bool
    {
        $reply = $ttlSeconds === null
            ? $this->client->set($key, $value)
            : $this->client->setex($key, $ttlSeconds, $value);

        return $reply !== false;
    }

    public function delete(string ...$keys): int
    {
        if ($keys === []) {
            return 0;
        }

        return (int) $this->client->del(...$keys);
    }

    public function keys(string $pattern): array
    {
        $result = $this->client->keys($pattern);

        return is_array($result) ? array_values(array_map('strval', $result)) : [];
    }

    public function close(): void
    {
        if (method_exists($this->client, 'disconnect')) {
            $this->client->disconnect();
        }
    }
}
