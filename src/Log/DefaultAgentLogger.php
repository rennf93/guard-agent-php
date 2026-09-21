<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Log;

/**
 * Stream-backed default logger, quiet on `debug` unless the GUARD_AGENT_DEBUG
 * environment variable holds a truthy value (anything except 0/false/no/off,
 * case-insensitive). In CLI SAPI, debug/info go to stdout and warning/error
 * to stderr; in web SAPI every level goes through error_log() so telemetry
 * noise can never corrupt the HTTP response body.
 */
final class DefaultAgentLogger implements AgentLogger
{
    private const PREFIX = '[guardagent]';

    private readonly bool $debugEnabled;

    /** @var resource|null */
    private $stdoutHandle = null;

    /** @var resource|null */
    private $stderrHandle = null;

    public function __construct()
    {
        $raw = getenv('GUARD_AGENT_DEBUG');
        if ($raw === false || $raw === '') {
            $this->debugEnabled = false;
        } else {
            $this->debugEnabled = !in_array(strtolower(trim($raw)), ['0', 'false', 'no', 'off'], true);
        }
    }

    public function debug(string $message): void
    {
        if ($this->debugEnabled) {
            $this->write(true, self::PREFIX . ' ' . $message);
        }
    }

    public function info(string $message): void
    {
        $this->write(true, self::PREFIX . ' ' . $message);
    }

    public function warning(string $message): void
    {
        $this->write(false, self::PREFIX . ' ' . $message);
    }

    public function error(string $message): void
    {
        $this->write(false, self::PREFIX . ' ' . $message);
    }

    private function write(bool $toStdout, string $line): void
    {
        if (\PHP_SAPI !== 'cli') {
            error_log($line);
            return;
        }
        $handle = $this->stream($toStdout);
        if ($handle !== null) {
            @fwrite($handle, $line . \PHP_EOL);
        } else {
            error_log($line);
        }
    }

    /** @return resource|null */
    private function stream(bool $stdout)
    {
        if ($stdout) {
            if ($this->stdoutHandle === null) {
                $this->stdoutHandle = (\defined('STDOUT') && \STDOUT) ? \STDOUT : @fopen('php://stdout', 'wb');
            }

            return $this->stdoutHandle;
        }
        if ($this->stderrHandle === null) {
            $this->stderrHandle = (\defined('STDERR') && \STDERR) ? \STDERR : @fopen('php://stderr', 'wb');
        }

        return $this->stderrHandle;
    }
}
