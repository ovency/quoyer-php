<?php

declare(strict_types=1);

namespace Quoyer\Http;

/**
 * One HTTP response from the Quoyer API.
 *
 * Every object the SDK returns keeps the response it came from, so the
 * rate-limit, warning and deprecation headers stay readable:
 *
 *     $customer->getLastResponse()?->rateLimit()?->remaining;
 */
final class ApiResponse
{
    /** @var array<string, list<string>> Keyed by lowercased header name. */
    private array $headers = [];

    /**
     * @param  array<array-key, array<string>|string>  $headers  PSR-7 shape: name => values.
     * @param  array<array-key, mixed>|null  $json  The decoded body, or null when it is empty or not JSON.
     */
    public function __construct(
        public readonly int $statusCode,
        array $headers,
        public readonly string $body,
        public readonly ?array $json,
    ) {
        foreach ($headers as $name => $values) {
            $this->headers[strtolower((string) $name)] = array_values(array_map('strval', (array) $values));
        }
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values === [] ? null : implode(', ', $values);
    }

    /**
     * @return array<string, list<string>>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * The limit this call counted against. Null when the call was refused
     * before the rate limiter (a bad key, an unregistered store).
     */
    public function rateLimit(): ?RateLimit
    {
        $limit = $this->header('X-RateLimit-Limit');
        if ($limit === null || ! is_numeric($limit)) {
            return null;
        }

        return new RateLimit(
            limit: (int) $limit,
            remaining: (int) ($this->header('X-RateLimit-Remaining') ?? 0),
            tier: $this->header('X-RateLimit-Tier'),
        );
    }

    /**
     * Seconds to wait, from `Retry-After`. Null when absent or not a number.
     */
    public function retryAfter(): ?int
    {
        $value = $this->header('Retry-After');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }

    /**
     * `X-Quoyer-Warning`, e.g. `payment_past_due`. For the merchant's admin
     * and logs only: never show it to a shopper.
     */
    public function warning(): ?string
    {
        return $this->header('X-Quoyer-Warning');
    }

    public function isPaymentPastDue(): bool
    {
        return $this->warning() === 'payment_past_due';
    }

    /**
     * `X-Quoyer-Deprecation`: response fields that still work but will be removed.
     */
    public function deprecation(): ?string
    {
        return $this->header('X-Quoyer-Deprecation');
    }
}
