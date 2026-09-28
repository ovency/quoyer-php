<?php

declare(strict_types=1);

namespace Quoyer\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Quoyer\QuoyerClient;

/**
 * @method static \Quoyer\Resources\Me me()
 * @method static \Quoyer\Services\CustomerService customers()
 * @method static \Quoyer\Services\PointService points()
 * @method static \Quoyer\Services\RedemptionService redemptions()
 * @method static \Quoyer\Services\BucketService buckets()
 * @method static \Quoyer\Services\EarningRuleService earningRules()
 * @method static \Quoyer\Services\CatalogueService catalogue()
 * @method static \Quoyer\Services\CurrencyService currencies()
 * @method static \Quoyer\Services\ProgramService program()
 * @method static \Quoyer\Services\TierService tiers()
 * @method static \Quoyer\QuoyerClient withOptions(array<string, mixed> $overrides)
 * @method static \Quoyer\QuoyerClient forStore(string $storeUrl)
 * @method static \Quoyer\Http\ApiResponse request(string $method, string $path, array<string, mixed> $query = [], array<array-key, mixed>|null $body = null, bool $retryable = false)
 *
 * @see QuoyerClient
 */
final class Quoyer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return QuoyerClient::class;
    }
}
