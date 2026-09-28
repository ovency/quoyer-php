<?php

declare(strict_types=1);

namespace Quoyer\Http;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Quoyer\ClientOptions;
use Quoyer\Exceptions\ConnectionException;
use Quoyer\Exceptions\ExceptionFactory;
use Quoyer\Exceptions\UnexpectedResponseException;

/**
 * Sends one API call: authentication, headers, retries, error mapping.
 *
 * Retry policy:
 *  - A connection error, 502, 503 or 504 is retried with exponential backoff
 *    when the call is retryable (every read, and every write the API makes
 *    idempotent). Creating an earning rule or a currency, or deleting a
 *    customer, is never retried.
 *  - A 429 is retried for any call, because the API refuses it before doing
 *    anything, but only when `Retry-After` is within `max_retry_wait`.
 *    Longer waits throw RateLimitException at once: a storefront must not
 *    hold a shopper's request for a minute.
 *
 * @internal Use the services on QuoyerClient, or QuoyerClient::request().
 */
final class Requestor
{
    public function __construct(
        private readonly ClientOptions $options,
        private readonly ClientInterface $http,
    ) {}

    public function options(): ClientOptions
    {
        return $this->options;
    }

    /**
     * @param  string  $path  Under `/api/v1`, starting with `/`.
     * @param  array<string, mixed>  $query
     * @param  array<array-key, mixed>|null  $body  JSON body; null sends none.
     * @param  bool  $retryable  Whether a failed attempt may be repeated safely.
     * @param  list<int>  $acceptStatus  Error statuses this call treats as success (e.g. 409 on a repeated reversal).
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        bool $retryable = true,
        array $acceptStatus = [],
    ): ApiResponse {
        $url = $this->options->apiBase().$path;
        $queryString = Encoder::query($query);
        if ($queryString !== '') {
            $url .= '?'.$queryString;
        }

        $headers = [
            'Authorization' => 'Bearer '.$this->options->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->options->userAgent(),
        ];
        if ($this->options->storeUrl !== null) {
            $headers['X-Quoyer-Store'] = $this->options->storeUrl;
        }

        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = Encoder::body($body);
        }

        $attempt = 0;

        while (true) {
            $attempt++;
            $canRetry = $attempt <= $this->options->maxRetries;

            try {
                $psrResponse = $this->http->request($method, $url, [
                    RequestOptions::HEADERS => $headers,
                    RequestOptions::BODY => $payload,
                    RequestOptions::TIMEOUT => $this->options->timeout,
                    RequestOptions::CONNECT_TIMEOUT => $this->options->connectTimeout,
                    RequestOptions::HTTP_ERRORS => false,
                    RequestOptions::ALLOW_REDIRECTS => false,
                ]);
            } catch (GuzzleException $e) {
                if ($retryable && $canRetry) {
                    $this->sleep($this->backoff($attempt));

                    continue;
                }

                throw new ConnectionException(
                    sprintf('Could not reach Quoyer at %s: %s', $this->options->apiBase(), $e->getMessage()),
                    $e,
                );
            }

            $response = self::toApiResponse($psrResponse);
            $status = $response->statusCode;

            if ($status === 429 && $canRetry) {
                $wait = $response->retryAfter() ?? 1;
                if ($wait <= $this->options->maxRetryWait) {
                    $this->sleep($wait * 1000);

                    continue;
                }
            }

            if (in_array($status, [502, 503, 504], true) && $retryable && $canRetry) {
                $this->sleep($this->backoff($attempt));

                continue;
            }

            if ($status >= 300 && $status < 400) {
                throw new UnexpectedResponseException(
                    sprintf(
                        'Quoyer answered with a redirect (%d to %s). Check base_url: it must be the install\'s own https address.',
                        $status,
                        $response->header('Location') ?? 'nowhere',
                    ),
                    $response,
                );
            }

            if ($status >= 400 && ! in_array($status, $acceptStatus, true)) {
                throw ExceptionFactory::fromResponse($response);
            }

            return $response;
        }
    }

    private static function toApiResponse(ResponseInterface $response): ApiResponse
    {
        $raw = (string) $response->getBody();
        $json = null;

        if ($raw !== '') {
            try {
                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $json = is_array($decoded) ? $decoded : null;
            } catch (JsonException) {
                $json = null;
            }
        }

        return new ApiResponse($response->getStatusCode(), $response->getHeaders(), $raw, $json);
    }

    /**
     * Milliseconds before retry number $attempt: 500, 1000, 2000… capped at
     * 5 s, with ±25% jitter so many clients don't retry in step.
     */
    private function backoff(int $attempt): int
    {
        $base = min(5000, 500 * (2 ** ($attempt - 1)));

        return (int) round($base * random_int(75, 125) / 100);
    }

    private function sleep(int $milliseconds): void
    {
        if ($this->options->sleep !== null) {
            ($this->options->sleep)($milliseconds);

            return;
        }

        usleep($milliseconds * 1000);
    }
}
