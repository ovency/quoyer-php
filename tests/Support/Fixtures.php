<?php

declare(strict_types=1);

namespace Quoyer\Tests\Support;

/**
 * Responses taken from the examples in Quoyer's OpenAPI contract (v1.9),
 * which Quoyer's own suite checks against the running API.
 */
final class Fixtures
{
    /**
     * @return array<string, mixed>
     */
    public static function customer(array $overrides = []): array
    {
        return array_merge([
            'id' => 'cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX',
            'object' => 'customer',
            'external_id' => '1042',
            'external_source' => 'woocommerce',
            'email' => 'jane@example.com',
            'phone' => null,
            'card_number' => '4821773019',
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'birthday' => '1991-04-29',
            'balance' => ['points' => 349, 'value' => ['amount_minor_units' => 349, 'amount' => '3.49', 'currency' => 'EUR']],
            'last_activity_at' => '2026-09-26T22:10:35+00:00',
            'tier' => ['id' => 'tir_01M3HZ9A1B2C3D4E5F6G7H8J9K', 'name' => 'Silver', 'badge_color' => '#9CA3AF', 'multiplier' => 125, 'since' => '2026-09-20T08:15:00+00:00'],
            'referral_code' => 'K7M2QX9P',
            'locale' => 'ro',
            'email_opt_out' => false,
            'tier_progress' => ['qualifying_points' => 820, 'next_tier' => ['id' => 'tir_01M3HZ9A1B2C3D4E5F6G7H8J9M', 'name' => 'Gold', 'threshold' => 1500], 'points_needed' => 680],
            'created_at' => '2026-09-26T22:10:34+00:00',
            'updated_at' => '2026-09-26T22:10:35+00:00',
            'lifetime' => ['earned_points' => 349, 'redeemed_points' => 0, 'currency' => 'EUR'],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function bucket(array $overrides = []): array
    {
        return array_merge([
            'id' => 'bkt_01M3FJ0PXV6B3F7T4889FFW82X',
            'object' => 'point_bucket',
            'customer_id' => 'cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX',
            'earning_rule' => ['id' => 'rul_01KQBQ6SXQ0T1VE73A77ERZ7QP', 'type' => 'purchase', 'name' => 'Standard purchase earning'],
            'original_amount' => 49,
            'remaining_amount' => 49,
            'consumed_amount' => 0,
            'is_active' => true,
            'is_expired' => false,
            'is_fully_consumed' => false,
            'is_reversed' => false,
            'reversed_at' => null,
            'reversed_reason' => null,
            'reversed_by' => null,
            'issued_at' => '2026-09-26T22:10:34+00:00',
            'expires_at' => '2027-09-26T22:10:34+00:00',
            'expired_at' => null,
            'metadata' => ['amount' => 49.99, 'currency' => 'EUR', 'order_id' => 5531, 'computed_points' => 49],
            'created_at' => '2026-09-26T22:10:34+00:00',
            'updated_at' => '2026-09-26T22:10:34+00:00',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function creditResult(bool $wasNew = true): array
    {
        return [
            'object' => 'point_credit_result',
            'points_awarded' => 49,
            'was_new' => $wasNew,
            'bucket' => self::bucket(),
            'buckets' => [self::bucket()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function creditReversal(bool $newlyReversed = true): array
    {
        $bucket = self::bucket(['remaining_amount' => 0, 'is_active' => false, 'is_reversed' => true, 'reversed_at' => '2026-09-26T22:10:35+00:00', 'reversed_reason' => 'refund']);

        return [
            'object' => 'point_credit_reversal_result',
            'points_withdrawn' => $newlyReversed ? 49 : 0,
            'was_newly_reversed' => $newlyReversed,
            'bucket' => $bucket,
            'points_reversed' => $newlyReversed ? 49 : 0,
            'buckets' => [$bucket],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function redemption(array $overrides = []): array
    {
        return array_merge([
            'id' => 'red_01M3FJ0Q4A72RCAF19WPFWRC2A',
            'object' => 'redemption',
            'customer_id' => 'cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX',
            'type' => 'redemption',
            'status' => 'active',
            'points_redeemed' => 100,
            'monetary_value' => '1.00',
            'currency' => 'EUR',
            'coupon_code' => 'QYR-516688D8',
            'bucket_consumption' => [
                ['bucket_id' => 'bkt_01M3FJ0PXV6B3F7T4889FFW82X', 'amount' => 49],
                ['bucket_id' => 'bkt_01M3FJ0Q03FECY4ZGNV426WX9B', 'amount' => 51],
            ],
            'source_reference' => 'woocommerce_cart_7_1',
            'metadata' => ['cart_id' => 7, 'currency_code' => 'EUR', 'value_minor_units' => 100, 'point_value_at_time' => 1],
            'reversed_at' => null,
            'created_at' => '2026-09-26T22:10:35+00:00',
            'updated_at' => '2026-09-26T22:10:35+00:00',
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    public static function me(): array
    {
        return [
            'object' => 'me',
            'tenant' => [
                'id' => 'tnt_01KQ951JQ2HR2YXJXR1FMEN68H', 'name' => 'Example Cosmetics', 'subdomain' => 'example',
                'dashboard_url' => 'https://example.quoyer.com/admin', 'state' => 'active', 'plan' => 'monthly',
                'tier' => 'indie', 'is_founder' => true, 'trial_ends_at' => '2026-05-14T13:10:22+00:00',
            ],
            'platform' => ['name' => 'woocommerce', 'store_name' => 'Example', 'store_url' => 'https://shop.example.com', 'store_url_etld1' => 'example.com'],
            'api_key' => ['id' => 'key_01M3FJ0PNX3FNNBZX9P8DB9KVT', 'name' => 'WooCommerce shop', 'masked' => 'quoy_live_6ikb795j....9ClA', 'last_used_at' => '2026-09-26T22:10:34+00:00', 'created_at' => '2026-09-26T22:10:34+00:00'],
            'entitlements' => [
                'features' => ['offline_pos' => false, 'vip_tiers' => false, 'referrals' => false, 'data_export' => true, 'custom_domain' => false],
                'limits' => ['members_active' => 2000, 'members_total' => 10000, 'webhook_endpoints' => 5, 'platforms' => 1, 'api_per_min' => 300, 'api_per_month' => null],
            ],
            'limits' => ['max_customers' => 10000],
            'rate_limits' => ['read_per_minute' => 300, 'write_per_minute' => 60, 'bulk_per_minute' => 30],
            'program' => ['default_currency' => 'EUR', 'active_currencies' => ['RON', 'EUR'], 'earning_rules_count' => ['active' => 3, 'total' => 12]],
            'store' => null,
            'stores' => ['count' => 1, 'limit' => 1],
            'brand' => ['logo_url' => null, 'email_logo_url' => null, 'favicon_url' => null, 'accent' => '#7c3aed', 'powered_by_url' => 'https://quoyer.com/?ref=01M3FJ0PNX3FNNBZX9P8DB9KVT'],
            'documentation_url' => 'https://quoyer.com/api/docs',
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array<string, mixed>
     */
    public static function list(array $data = [], ?string $nextCursor = null): array
    {
        return ['object' => 'list', 'data' => $data, 'has_more' => $nextCursor !== null, 'next_cursor' => $nextCursor];
    }

    /**
     * @return array<string, mixed>
     */
    public static function error(string $type, string $code, string $message = 'Something went wrong.', array $extra = []): array
    {
        return ['error' => array_merge(['type' => $type, 'code' => $code, 'message' => $message], $extra)];
    }
}
