<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Persistence;

use RenzoFranceschini\GuardAgent\Exception\RedisException;

/**
 * Dependency-free Redis client speaking RESP2 over a PHP stream socket,
 * mirroring the command surface the Python agent's RedisHandlerProtocol uses
 * (guard_agent/protocols.py:6-46). This is the default persistence backend:
 * streams are core PHP, so crash-recovery works with zero composer and zero
 * PECL dependencies, exactly like the TypeScript port's built-in usage of a
 * plain socket-level client is avoided in favour of ioredis but the Go port
 * uses the stdlib HTTP client. No pipeline, no pub/sub, no cluster: PING,
 * AUTH, SELECT, GET, SET (EX), DEL, KEYS.
 *
 * Every failure raises RedisException; the RedisHandler catches it and fails
 * open.
 */
final class StreamRedisClient implements RedisClientInterface
{
    /** @var resource|null */
    private $stream = null;

    private readonly string $host;

    private readonly int $port;

    private readonly ?string $password;

    private readonly ?int $db;

    private readonly bool $tls;

    private readonly float $timeoutSeconds;

    /**
     * @param string $url redis:// or rediss:// URL, e.g. redis://localhost:6379/0
     * @param string|null $password overrides the password embedded in the URL
     * @param int|null $db overrides the database embedded in the URL
     * @param float $timeoutSeconds per-command timeout
     *
     * @throws RedisException
     */
    public function __construct(
        string $url,
        ?string $password = null,
        ?int $db = null,
        float $timeoutSeconds = 5.0,
    ) {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new RedisException("Invalid redis URL: {$url}");
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'redis' && $scheme !== 'rediss') {
            throw new RedisException("Unsupported redis scheme '{$scheme}' in {$url}");
        }
        $this->tls = $scheme === 'rediss';
        $this->host = (string) $parts['host'];
        $this->port = isset($parts['port']) ? (int) $parts['port'] : 6379;
        $urlPassword = $parts['pass'] ?? null;
        $this->password = $password !== null && $password !== '' ? $password : $urlPassword;
        $path = ltrim((string) ($parts['path'] ?? ''), '/');
        $this->db = $db ?? ($path !== '' && is_numeric($path) ? (int) $path : null);
        $this->timeoutSeconds = max(0.1, $timeoutSeconds);
    }

    public function ping(): void
    {
        $reply = $this->command('PING');
        if ($reply !== 'PONG') {
            throw new RedisException('Unexpected PING reply: ' . var_export($reply, true));
        }
    }

    public function get(string $key): ?string
    {
        $reply = $this->command('GET', $key);

        return is_string($reply) ? $reply : null;
    }

    public function set(string $key, string $value, ?int $ttlSeconds = null): bool
    {
        $args = $ttlSeconds === null ? [$key, $value] : [$key, $value, 'EX', (string) $ttlSeconds];
        $reply = $this->command('SET', ...$args);

        return $reply === 'OK';
    }

    public function delete(string ...$keys): int
    {
        if ($keys === []) {
            return 0;
        }
        $reply = $this->command('DEL', ...$keys);

        return is_int($reply) ? $reply : 0;
    }

    public function keys(string $pattern): array
    {
        $reply = $this->command('KEYS', $pattern);
        if (!is_array($reply)) {
            return [];
        }
        $keys = [];
        foreach ($reply as $item) {
            if (is_string($item)) {
                $keys[] = $item;
            }
        }

        return $keys;
    }

    public function close(): void
    {
        if ($this->stream === null) {
            return;
        }
        $stream = $this->stream;
        $this->stream = null;
        try {
            $this->write($stream, "*1\r\n\$4\r\nQUIT\r\n");
        } catch (\Throwable) {
            // Best effort; the socket is going away regardless.
        }
        if (is_resource($stream)) {
            @fclose($stream);
        }
    }

    /**
     * Send one command and return the decoded reply.
     *
     * @throws RedisException
     */
    private function command(string ...$args): mixed
    {
        $stream = $this->connect();
        $payload = '*' . count($args) . "\r\n";
        foreach ($args as $arg) {
            $payload .= '$' . strlen($arg) . "\r\n" . $arg . "\r\n";
        }
        $this->write($stream, $payload);

        return $this->readReply($stream);
    }

    /** @return resource */
    private function connect()
    {
        if ($this->stream !== null) {
            return $this->stream;
        }

        $target = sprintf('%s%s:%d', $this->tls ? 'ssl://' : 'tcp://', $this->host, $this->port);
        $context = stream_context_create($this->tls ? ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]] : []);
        $stream = @stream_socket_client(
            $target,
            $errorCode,
            $errorMessage,
            $this->timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($stream === false) {
            throw new RedisException("redis.connect failed for {$target}: {$errorCode} {$errorMessage}");
        }
        stream_set_timeout($stream, (int) $this->timeoutSeconds, (int) (($this->timeoutSeconds - (int) $this->timeoutSeconds) * 1_000_000));
        $this->stream = $stream;

        if ($this->password !== null && $this->password !== '') {
            $reply = $this->command('AUTH', $this->password);
            if ($reply !== 'OK') {
                $this->forget();
                throw new RedisException('redis AUTH failed');
            }
        }
        if ($this->db !== null && $this->db !== 0) {
            $reply = $this->command('SELECT', (string) $this->db);
            if ($reply !== 'OK') {
                $this->forget();
                throw new RedisException("redis SELECT {$this->db} failed");
            }
        }

        return $stream;
    }

    private function forget(): void
    {
        if ($this->stream !== null) {
            @fclose($this->stream);
            $this->stream = null;
        }
    }

    /**
     * @param resource $stream
     *
     * @throws RedisException
     */
    private function write($stream, string $payload): void
    {
        $written = @fwrite($stream, $payload);
        if ($written === false || $written < strlen($payload)) {
            $this->forget();
            throw new RedisException('redis write failed (connection lost?)');
        }
    }

    /**
     * @param resource $stream
     *
     * @throws RedisException
     */
    private function readReply($stream): mixed
    {
        $line = $this->readLine($stream);
        if ($line === '' || $line === false) {
            $this->forget();
            throw new RedisException('redis read failed (connection lost?)');
        }
        $type = $line[0];
        $rest = substr($line, 1);

        switch ($type) {
            case '+':
                return $rest;
            case '-':
                throw new RedisException("redis error reply: {$rest}");
            case ':':
                return (int) $rest;
            case '$':
                $length = (int) $rest;
                if ($length === -1) {
                    return null;
                }
                $data = '';
                $remaining = $length + 2;
                while ($remaining > 0) {
                    $chunk = @fread($stream, $remaining);
                    if ($chunk === false || $chunk === '') {
                        $this->forget();
                        throw new RedisException('redis read failed (bulk string truncated)');
                    }
                    $data .= $chunk;
                    $remaining -= strlen($chunk);
                }

                return substr($data, 0, $length);
            case '*':
                $count = (int) $rest;
                if ($count === -1) {
                    return null;
                }
                $items = [];
                for ($i = 0; $i < $count; $i++) {
                    $items[] = $this->readReply($stream);
                }

                return $items;
            default:
                $this->forget();
                throw new RedisException('redis protocol error: ' . $line);
        }
    }

    /**
     * @param resource $stream
     *
     * @throws RedisException
     */
    private function readLine($stream): string|false
    {
        $line = @fgets($stream);
        if ($line === false) {
            return false;
        }
        $meta = stream_get_meta_data($stream);
        if (($meta['timed_out'] ?? false) === true) {
            $this->forget();
            throw new RedisException('redis command timed out');
        }

        return rtrim($line, "\r\n");
    }
}
