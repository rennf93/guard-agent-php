<?php

declare(strict_types=1);

/**
 * basic_usage wires guard-agent-php into a guard-core-php engine the way a
 * production service would: the engine's onBlock hook builds a SecurityEvent
 * per blocked request, the agent buffers and ships it to the Guard Core App
 * ingestion API, and shutdown performs a final flush.
 *
 * The agent never imports guard-core-php; this file encodes only the onBlock
 * payload contract (see guard-core-php's src/Pipeline/BlockEvents.php). In a
 * real service the closure built by blockEventFromPayload() is handed to
 * SecurityConfig(onBlock: ...) before GuardEngine is constructed, by your
 * middleware or an adapter (psr15-guard, slim-guard, laravel-guard,
 * symfony-guard).
 *
 * Set GUARD_AGENT_API_KEY (required), GUARD_AGENT_PROJECT_ID, and
 * GUARD_AGENT_SIGNING_SECRET before running. Optional persistence:
 * GUARD_AGENT_REDIS_URL.
 *
 *   php examples/basic_usage/basic_usage.php
 */

use RenzoFranceschini\GuardAgent\Config\AgentConfigResolver;
use RenzoFranceschini\GuardAgent\Config\RedisConfig;
use RenzoFranceschini\GuardAgent\GuardAgent;

require __DIR__ . '/../../vendor/autoload.php';

$apiKey = getenv('GUARD_AGENT_API_KEY');
if ($apiKey === false || $apiKey === '') {
    fwrite(STDERR, "GUARD_AGENT_API_KEY is required\n");
    exit(1);
}

$input = [
    'apiKey' => $apiKey,
    'projectId' => getenv('GUARD_AGENT_PROJECT_ID') ?: null,
    'payloadSigningSecret' => getenv('GUARD_AGENT_SIGNING_SECRET') ?: null,
    'guardVersion' => 'example',
];

$redisUrl = getenv('GUARD_AGENT_REDIS_URL');
if ($redisUrl !== false && $redisUrl !== '') {
    $input['redis'] = ['url' => $redisUrl];
}

$agent = new GuardAgent(AgentConfigResolver::resolve($input));

// start() is the only method that may throw (config validation happens in
// the resolver; start() can fail on crash-recovery load). Everything else
// never throws into the host path.
$agent->start();

/**
 * Builds the agent's SecurityEvent input array from a guard-core-php
 * onBlock payload. The engine fires the hook for every block or passive
 * detection verdict with a flat map: check_name, reason, trigger_info,
 * passive_mode, client_ip, path, method, status_code. Both camelCase and
 * snake_case keys are accepted by the models; this mapping is the seam the
 * adapters replicate.
 *
 * @param array<string, mixed> $payload
 *
 * @return array<string, mixed>
 */
function blockEventFromPayload(array $payload): array
{
    return [
        // The onBlock payload carries no timestamp; the wiring stamps it
        // at hook time, the way the adapters do.
        'timestamp' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        'event_type' => 'suspicious_request',
        'ip_address' => (string) ($payload['client_ip'] ?? ''),
        'endpoint' => (string) ($payload['path'] ?? ''),
        'method' => (string) ($payload['method'] ?? ''),
        'action_taken' => 'BLOCKED',
        'reason' => (string) ($payload['reason'] ?? ''),
        'status_code' => is_int($payload['status_code'] ?? null) ? $payload['status_code'] : null,
        'metadata' => [
            'check_name' => (string) ($payload['check_name'] ?? ''),
            'trigger_info' => (string) ($payload['trigger_info'] ?? ''),
            'passive_mode' => (bool) ($payload['passive_mode'] ?? false),
        ],
    ];
}

// Engine wiring: assign the hook to SecurityConfig(onBlock: ...) before
// constructing guard-core-php's GuardEngine, as a real service would. Here
// we simulate the engine side by feeding one representative payload so the
// example visibly enqueues an event.
$onBlock = static function (array $payload) use ($agent): void {
    $agent->sendEvent(blockEventFromPayload($payload));
};

$onBlock([
    'check_name' => 'rate_limit',
    'reason' => 'Rate limit exceeded: 31/60 (global tier)',
    'trigger_info' => 'rate_limit',
    'passive_mode' => false,
    'client_ip' => '203.0.113.7',
    'path' => '/api',
    'method' => 'GET',
    'status_code' => 429,
]);

// Long-running workers drive the agent from their own loop with tick():
// cheap when no trigger fires, flushes on the high-watermark or the flush
// interval, pushes status reports on the status interval. Request-scoped
// apps skip the loop entirely and call flushBuffer() from
// kernel.terminate / register_shutdown_function instead.
for ($i = 0; $i < 3; $i++) {
    $agent->tick();
    usleep(1_000_000);
}

printf(
    "agent healthy: %s (stats: %s)\n",
    $agent->healthCheck() ? 'true' : 'false',
    json_encode($agent->getStats()),
);

// Shutdown: final flush, stop loops, release Redis.
$agent->stop();
