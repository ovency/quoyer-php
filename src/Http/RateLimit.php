<?php

declare(strict_types=1);

namespace Quoyer\Http;

/**
 * The per-minute limit a call counted against. Limits are per merchant: every
 * key of the merchant shares them. Writes get a fifth of the read limit.
 */
final class RateLimit
{
    public function __construct(
        public readonly int $limit,
        public readonly int $remaining,
        /** `read`, `write` or `bulk`. */
        public readonly ?string $tier,
    ) {}
}
