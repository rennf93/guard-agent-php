<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Install;

use RenzoFranceschini\GuardAgent\Log\AgentLogger;
use RenzoFranceschini\GuardAgent\Utils\Uuid;

/**
 * Install ID resolution, mirroring guard_agent/install_id.py.
 *
 * The install ID is a stable per-installation UUID sent as
 * `X-Agent-Install-Id` and used server-side for install tracking
 * (guard-core-api guard_core_api/api/routers/telemetry_router.py:194). It is
 * cached on disk at ~/.guard-agent/install-id; every filesystem failure falls
 * back to a fresh in-memory UUID rather than raising, because telemetry must
 * never break the host application.
 */
final class InstallId
{
    private function __construct()
    {
    }

    /** Default on-disk location of the install ID. */
    public static function defaultPath(): string
    {
        $home = getenv('HOME');
        if ($home === false || $home === '') {
            $home = $_SERVER['HOME'] ?? sys_get_temp_dir();
        }

        return rtrim($home, '/') . '/.guard-agent/install-id';
    }

    /**
     * Resolve the install ID: an explicit override wins, then the cached
     * value, otherwise a new UUID is generated and cached.
     */
    public static function resolve(?string $statePath = null, ?string $override = null, ?AgentLogger $logger = null): string
    {
        if ($override !== null && $override !== '') {
            return $override;
        }

        $logger ??= new \RenzoFranceschini\GuardAgent\Log\DefaultAgentLogger();
        $path = $statePath ?? self::defaultPath();

        $existing = self::read($path, $logger);
        if ($existing !== null) {
            return $existing;
        }

        $created = Uuid::v4();
        self::write($path, $created, $logger);

        return $created;
    }

    private static function read(string $path, AgentLogger $logger): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        try {
            $contents = @file_get_contents($path);
            if ($contents === false) {
                $logger->warning("install_id.read_failed path={$path}");

                return null;
            }
            $trimmed = trim($contents);

            return $trimmed !== '' ? $trimmed : null;
        } catch (\Throwable $exception) {
            $logger->warning("install_id.read_failed path={$path}: " . $exception->getMessage());

            return null;
        }
    }

    private static function write(string $path, string $installId, AgentLogger $logger): void
    {
        try {
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
                $logger->warning("install_id.write_failed path={$path}");

                return;
            }
            if (@file_put_contents($path, $installId) === false) {
                $logger->warning("install_id.write_failed path={$path}");
            }
        } catch (\Throwable $exception) {
            $logger->warning("install_id.write_failed path={$path}: " . $exception->getMessage());
        }
    }
}
