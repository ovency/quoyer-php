<?php

declare(strict_types=1);

namespace Quoyer;

use Closure;
use Quoyer\Exceptions\InvalidArgumentException;

/**
 * The client's configuration, validated once and immutable afterwards.
 *
 * Build it from an array (the form QuoyerClient and the Laravel config use):
 *
 *     ClientOptions::fromArray(['api_key' => 'quoy_live_…', 'store_url' => 'https://shop.example.com']);
 */
final class ClientOptions
{
    public const DEFAULT_BASE_URL = 'https://quoyer.com';

    /** The keys fromArray() accepts. */
    public const KEYS = [
        'api_key',
        'base_url',
        'store_url',
        'platform',
        'timeout',
        'connect_timeout',
        'max_retries',
        'max_retry_wait',
        'sleep',
    ];

    /**
     * @param  string  $apiKey  The merchant's key, `quoy_live_…` or `quoy_sandbox_…`.
     * @param  string  $baseUrl  The Quoyer install, e.g. `https://quoyer.com`. `/api/v1` is added for you.
     * @param  string|null  $storeUrl  Sent as `X-Quoyer-Store` on every call. Set it only for a storefront (see README, "Connected stores").
     * @param  string|null  $platform  `name/version`, e.g. `myshop/2.1.0`. Sent as `Quoyer-myshop/2.1.0` in the User-Agent, which names the platform of a connected store.
     * @param  float  $timeout  Seconds to wait for a whole response.
     * @param  float  $connectTimeout  Seconds to wait for the connection.
     * @param  int  $maxRetries  Retries after the first attempt, for connection errors, 502/503/504 and short 429s.
     * @param  int  $maxRetryWait  The longest `Retry-After` (seconds) the client will sleep through on a 429. Longer waits throw RateLimitException at once.
     * @param  Closure(int): void|null  $sleep  Receives milliseconds. For tests; defaults to usleep().
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly ?string $storeUrl = null,
        public readonly ?string $platform = null,
        public readonly float $timeout = 10.0,
        public readonly float $connectTimeout = 3.0,
        public readonly int $maxRetries = 2,
        public readonly int $maxRetryWait = 5,
        public readonly ?Closure $sleep = null,
    ) {
        $this->validate();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public static function fromArray(array $options): self
    {
        $unknown = array_diff(array_keys($options), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown Quoyer client option(s): %s. Allowed: %s.',
                implode(', ', $unknown),
                implode(', ', self::KEYS),
            ));
        }

        $apiKey = $options['api_key'] ?? null;
        if (! is_string($apiKey)) {
            throw new InvalidArgumentException('The Quoyer client needs an api_key (a string, e.g. "quoy_live_…").');
        }

        $sleep = $options['sleep'] ?? null;
        if ($sleep !== null && ! is_callable($sleep)) {
            throw new InvalidArgumentException('The sleep option must be callable.');
        }

        return new self(
            apiKey: trim($apiKey),
            baseUrl: self::string($options, 'base_url') ?? self::DEFAULT_BASE_URL,
            storeUrl: self::string($options, 'store_url'),
            platform: self::string($options, 'platform'),
            timeout: (float) ($options['timeout'] ?? 10.0),
            connectTimeout: (float) ($options['connect_timeout'] ?? 3.0),
            maxRetries: (int) ($options['max_retries'] ?? 2),
            maxRetryWait: (int) ($options['max_retry_wait'] ?? 5),
            sleep: $sleep === null ? null : Closure::fromCallable($sleep),
        );
    }

    /**
     * The same options with some replaced.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->toArray(), $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'base_url' => $this->baseUrl,
            'store_url' => $this->storeUrl,
            'platform' => $this->platform,
            'timeout' => $this->timeout,
            'connect_timeout' => $this->connectTimeout,
            'max_retries' => $this->maxRetries,
            'max_retry_wait' => $this->maxRetryWait,
            'sleep' => $this->sleep,
        ];
    }

    /**
     * The API root every path is appended to, e.g. `https://quoyer.com/api/v1`.
     */
    public function apiBase(): string
    {
        $base = rtrim($this->baseUrl, '/');

        return str_ends_with($base, '/api/v1') ? $base : $base.'/api/v1';
    }

    /**
     * `Quoyer-{platform} quoyer-php/{version} PHP/{version}`. Quoyer reads the
     * platform of a connected store from a leading `Quoyer-{name}/`; without
     * a platform the SDK names none.
     */
    public function userAgent(): string
    {
        $agent = sprintf('quoyer-php/%s PHP/%s', QuoyerClient::VERSION, PHP_VERSION);

        return $this->platform === null ? $agent : 'Quoyer-'.$this->platform.' '.$agent;
    }

    /**
     * The key for logs: its public prefix and last four characters.
     */
    public function maskedApiKey(): string
    {
        $dot = strpos($this->apiKey, '.');
        $prefix = $dot !== false ? substr($this->apiKey, 0, $dot) : substr($this->apiKey, 0, 12);

        return $prefix.'.****'.substr($this->apiKey, -4);
    }

    private function validate(): void
    {
        if ($this->apiKey === '') {
            throw new InvalidArgumentException('The Quoyer API key is empty. Create one in the Quoyer dashboard under Integration → API keys.');
        }

        if (preg_match('/\s/', $this->apiKey) === 1) {
            throw new InvalidArgumentException('The Quoyer API key contains whitespace. Copy it again from the dashboard.');
        }

        $this->validateBaseUrl();

        if ($this->storeUrl !== null && (strlen($this->storeUrl) > 2048 || preg_match('/[\r\n]/', $this->storeUrl) === 1)) {
            throw new InvalidArgumentException('store_url must be a single line of at most 2,048 characters.');
        }

        if ($this->platform !== null && preg_match('#^[A-Za-z0-9]+/[A-Za-z0-9._+\-]+$#', $this->platform) !== 1) {
            throw new InvalidArgumentException('platform must look like "name/version", e.g. "myshop/2.1.0" (letters and digits in the name).');
        }

        if ($this->timeout <= 0 || $this->connectTimeout <= 0) {
            throw new InvalidArgumentException('timeout and connect_timeout must be greater than zero.');
        }

        if ($this->maxRetries < 0 || $this->maxRetryWait < 0) {
            throw new InvalidArgumentException('max_retries and max_retry_wait cannot be negative.');
        }
    }

    /**
     * HTTPS only, except on a local development host: the API key travels in
     * a header and must never cross the network in clear text.
     */
    private function validateBaseUrl(): void
    {
        $parts = parse_url($this->baseUrl);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        $host = is_array($parts) ? strtolower($parts['host'] ?? '') : '';

        if ($host === '' || ! in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidArgumentException(sprintf('base_url "%s" is not a URL. Use the Quoyer install\'s address, e.g. https://quoyer.com.', $this->baseUrl));
        }

        $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost');

        if ($scheme === 'http' && ! $local) {
            throw new InvalidArgumentException('base_url must use https. Plain http is allowed only for localhost and *.test development hosts.');
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private static function string(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new InvalidArgumentException(sprintf('The %s option must be a string.', $key));
        }
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
