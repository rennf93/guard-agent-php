<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Transport;

/**
 * HMAC payload signing, mirroring guard_agent/signing.py.
 *
 * When `payloadSigningSecret` is configured the transport sends
 * `X-Payload-Signature: v1=<hex>`, where <hex> is the HMAC-SHA256 of the
 * UNCOMPRESSED JSON body bytes.
 *
 * IMPORTANT (this port differs from the Python/TypeScript/Go agents on
 * purpose): the ingestion API verifies the signature after decompression.
 * guard-core-api installs a GzipRequestMiddleware that inflates
 * `Content-Encoding: gzip` request bodies before the telemetry router runs,
 * so `_verify_signature_or_warn` (telemetry_router.py:110-126) computes the
 * HMAC over the decompressed bytes. The PHP agent therefore signs the exact
 * uncompressed body the server will hash, which verifies clean even when
 * compression applies.
 */
final class Signer
{
    private const VERSION_PREFIX = 'v1=';

    private function __construct()
    {
    }

    /** Sign a request body. Returns null when no secret is configured. */
    public static function signPayload(string $body, ?string $secret): ?string
    {
        if ($secret === null || $secret === '') {
            return null;
        }
        $digest = hash_hmac('sha256', $body, $secret);

        return self::VERSION_PREFIX . $digest;
    }
}
