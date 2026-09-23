# basic_usage

Minimal guard-agent-php wiring: a guard-core-php engine's `onBlock` hook
builds a security event per blocked request and the agent buffers and ships
it to the Guard Core App ingestion API.

## Run

```bash
export GUARD_AGENT_API_KEY="your-api-key"
export GUARD_AGENT_PROJECT_ID="your-project-id"
export GUARD_AGENT_SIGNING_SECRET="your-signing-secret"   # optional, enables HMAC signing
export GUARD_AGENT_REDIS_URL="redis://localhost:6379/0"   # optional, enables persistence

composer install
php examples/basic_usage/basic_usage.php
```

## The wiring path

`basic_usage.php` demonstrates three pieces:

1. Agent construction from the environment through
   `AgentConfigResolver::resolve()` (required `apiKey`, optional
   `projectId`, `payloadSigningSecret`, and `redis`), followed by
   `start()`.
2. The engine seam: guard-core-php exposes `SecurityConfig(onBlock: ...)`,
   fired for every block or passive-detection verdict with a flat payload
   (`check_name`, `reason`, `trigger_info`, `passive_mode`, `client_ip`,
   `path`, `method`, `status_code`). The `blockEventFromPayload()` function
   maps that payload onto the agent's event input array. In a real service
   this closure is assigned to `SecurityConfig(onBlock: ...)` before
   `GuardEngine` is constructed, by your middleware or by an adapter
   (psr15-guard, slim-guard, laravel-guard, symfony-guard).
3. The host-driven runtime: `tick()` calls from a worker loop, and
   `stop()` for the final flush on shutdown. Request-scoped apps call
   `flushBuffer()` from `kernel.terminate` or `register_shutdown_function`
   instead of running a loop.

The example stays a wiring demonstration: it feeds one representative
payload so an event is visibly enqueued, ticks a few times, reports health
and stats, and performs the final flush. Without a valid API key the flush
attempt fails with a logged 401 and the event is requeued (fail-soft by
design); point `endpoint` at your guard-core-app deployment to deliver
for real. For a full guarded HTTP service, see the adapter repos, which
combine the engine, the adapter, and this same agent wiring.

## Notes

- The direct agent API (`sendEvent`, `sendMetric`, `getStatus`, `getStats`)
  is for custom events or standalone deployments; most guard-core-php
  deployments only need the hook shown here.
- The agent never imports guard-core-php: the mapping in
  `blockEventFromPayload()` encodes only the payload contract, so the
  two packages stay decoupled at the composer level.
- With `GUARD_AGENT_SIGNING_SECRET` set, the HMAC covers the uncompressed
  JSON body; the ingestion API verifies the signature after decompression.
