<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * 429 `too_many_requests`: over the merchant's per-minute limit. Wait
 * getRetryAfter() seconds; do not retry in a loop.
 *
 * The client already sleeps through short waits (`max_retry_wait`, 5 s by
 * default); this is thrown when the wait is longer or retries ran out.
 */
class RateLimitException extends ApiException
{
    public function getRetryAfter(): ?int
    {
        return $this->getResponse()?->retryAfter();
    }
}
