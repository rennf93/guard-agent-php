<?php

declare(strict_types=1);

namespace RenzoFranceschini\GuardAgent\Utils;

use RenzoFranceschini\GuardAgent\Utils\Uuid;

/**
 * Unique batch ID, mirroring generate_batch_id (guard_agent/utils.py:39-43):
 * epoch-milliseconds + "-" + 8 hex characters.
 */
final class BatchId
{
    private function __construct()
    {
    }

    public static function generate(): string
    {
        $timestampMillis = (string) (int) (microtime(true) * 1000);
        $randomPart = substr(Uuid::hex(), 0, 8);

        return $timestampMillis . '-' . $randomPart;
    }
}
