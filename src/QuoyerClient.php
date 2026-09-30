<?php

declare(strict_types=1);

namespace Quoyer;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Http\ApiResponse;
use Quoyer\Http\Requestor;
use Quoyer\Resources\Me;
use Quoyer\Services\BucketService;
use Quoyer\Services\CatalogueService;
use Quoyer\Services\CurrencyService;
use Quoyer\Services\CustomerService;
use Quoyer\Services\EarningRuleService;
use Quoyer\Services\PointService;
use Quoyer\Services\ProgramService;
use Quoyer\Services\RedemptionService;
use Quoyer\Services\RewardService;
use Quoyer\Services\TierService;

/**
 * The Quoyer API client.
 *
 *     $quoyer = new QuoyerClient('quoy_live_…');
 *
 *     $quoyer = new QuoyerClient([
 *         'api_key'   => getenv('QUOYER_API_KEY'),
 *         'base_url'  => 'https://quoyer.com',
 *         'store_url' => 'https://shop.example.com',   // storefronts only
 *     ]);
 *
 *     $customer = $quoyer->customers->upsert([...]);
 *     $result   = $quoyer->points->credit([...]);
 *
 * Services are properties (`$quoyer->customers`) and methods
 * (`$quoyer->customers()`), so the Laravel facade works too:
 * `Quoyer::customers()->upsert([...])`.
 */
final class QuoyerClient
{
    /** This SDK's version. */
    public const VERSION = '1.1.0';

    /** The API contract version this SDK release covers in full. */
    public const API_CONTRACT = '1.14';

    public readonly CustomerService $customers;

    public readonly PointService $points;

    public readonly RedemptionService $redemptions;

    public readonly RewardService $rewards;

    public readonly BucketService $buckets;

    public readonly EarningRuleService $earningRules;

    public readonly CatalogueService $catalogue;

    public readonly CurrencyService $currencies;

    public readonly ProgramService $program;

    public readonly TierService $tiers;

    private readonly ClientOptions $options;

    private readonly ClientInterface $http;

    private readonly Requestor $requestor;

    /**
     * @param  string|array<string, mixed>|ClientOptions  $options  An API key, or options (see ClientOptions::KEYS).
     * @param  ClientInterface|null  $httpClient  A Guzzle client to send through (proxies, custom TLS, test mocks).
     */
    public function __construct(string|array|ClientOptions $options, ?ClientInterface $httpClient = null)
    {
        $this->options = match (true) {
            $options instanceof ClientOptions => $options,
            is_string($options) => ClientOptions::fromArray(['api_key' => $options]),
            default => ClientOptions::fromArray($options),
        };

        $this->http = $httpClient ?? new GuzzleClient;
        $this->requestor = new Requestor($this->options, $this->http);

        $this->customers = new CustomerService($this->requestor);
        $this->points = new PointService($this->requestor);
        $this->redemptions = new RedemptionService($this->requestor);
        $this->rewards = new RewardService($this->requestor);
        $this->buckets = new BucketService($this->requestor);
        $this->earningRules = new EarningRuleService($this->requestor);
        $this->catalogue = new CatalogueService($this->requestor);
        $this->currencies = new CurrencyService($this->requestor);
        $this->program = new ProgramService($this->requestor);
        $this->tiers = new TierService($this->requestor);
    }

    /**
     * A copy of this client with some options replaced, sharing the HTTP
     * client. E.g. another merchant's key in a multi-merchant app:
     *
     *     $quoyer->withOptions(['api_key' => $merchant->quoyer_key]);
     *
     * @param  array<string, mixed>  $overrides
     */
    public function withOptions(array $overrides): self
    {
        return new self($this->options->with($overrides), $this->http);
    }

    /**
     * A copy of this client that sends `X-Quoyer-Store` for this storefront
     * (a multishop or multisite sends each shop's own URL).
     */
    public function forStore(string $storeUrl): self
    {
        return $this->withOptions(['store_url' => $storeUrl]);
    }

    public function options(): ClientOptions
    {
        return $this->options;
    }

    /**
     * Who this key belongs to and what the merchant's plan allows. Call it
     * first: it confirms the key works and, with a store_url, registers the
     * storefront (a new store past the plan's limit throws
     * AccountStateException `store_limit_reached`: show the merchant
     * getAdminReason() on your connection screen).
     */
    public function me(): Me
    {
        $response = $this->requestor->request('GET', '/me');

        if ($response->json === null) {
            throw new Exceptions\UnexpectedResponseException('Expected a JSON object from GET /me.', $response);
        }

        return Me::constructFrom($response->json, $response);
    }

    /**
     * Call any endpoint directly, for one newer than this SDK. Errors throw
     * the same exceptions as the services; the answer is not converted.
     *
     *     $response = $quoyer->request('GET', '/locations', ['limit' => 10]);
     *     $response->json;
     *
     * @param  string  $path  Under `/api/v1`, e.g. `/customers`.
     * @param  array<string, mixed>  $query
     * @param  array<array-key, mixed>|null  $body
     * @param  bool  $retryable  Only true when repeating the call is harmless.
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, bool $retryable = false): ApiResponse
    {
        if (! str_starts_with($path, '/')) {
            throw new InvalidArgumentException('The path must start with "/", e.g. "/customers".');
        }

        return $this->requestor->request(strtoupper($method), $path, $query, $body, $retryable || strtoupper($method) === 'GET');
    }

    public function customers(): CustomerService
    {
        return $this->customers;
    }

    public function points(): PointService
    {
        return $this->points;
    }

    public function redemptions(): RedemptionService
    {
        return $this->redemptions;
    }

    public function rewards(): RewardService
    {
        return $this->rewards;
    }

    public function buckets(): BucketService
    {
        return $this->buckets;
    }

    public function earningRules(): EarningRuleService
    {
        return $this->earningRules;
    }

    public function catalogue(): CatalogueService
    {
        return $this->catalogue;
    }

    public function currencies(): CurrencyService
    {
        return $this->currencies;
    }

    public function program(): ProgramService
    {
        return $this->program;
    }

    public function tiers(): TierService
    {
        return $this->tiers;
    }
}
