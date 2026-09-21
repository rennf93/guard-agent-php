<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Config;

/**
 * Redis connection options for crash-recovery persistence, mirroring the
 * RedisConfig block of AgentConfig (guard_agent/models.py). The URL may carry
 * the password (`redis://:secret@host:port/0`); the explicit password and db
 * fields win when both are given.
 */
final class RedisConfig
{
    /**
     * @param array<string, mixed> $input
     */
    public static function fromArray(array $input): self
    {
        return new self(
            url: (string) ($input['url'] ?? ''),
            keyPrefix: isset($input['keyPrefix']) ? (string) $input['keyPrefix'] : (isset($input['key_prefix']) ? (string) $input['key_prefix'] : 'guard:agent'),
            password: isset($input['password']) ? (string) $input['password'] : null,
            db: isset($input['db']) ? (int) $input['db'] : null,
            commandTimeoutMs: isset($input['commandTimeoutMs']) ? (float) $input['commandTimeoutMs'] : (isset($input['command_timeout_ms']) ? (float) $input['command_timeout_ms'] : 5000.0),
        );
    }

    public function __construct(
        public readonly string $url,
        public readonly string $keyPrefix = 'guard:agent',
        public readonly ?string $password = null,
        public readonly ?int $db = null,
        public readonly float $commandTimeoutMs = 5000.0,
    ) {
    }
}
