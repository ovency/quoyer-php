<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * Points spent, usually as a coupon.
 *
 * `create()` adds `was_new`; `reverse()` adds `was_newly_reversed`,
 * `points_restored` and `bucket_restorations`.
 *
 * @property-read string $id `red_…`
 * @property-read string $object `redemption`
 * @property-read string|null $customer_id
 * @property-read string $type `redemption`, `manual_deduction` or (old records) `expiration`.
 * @property-read string $status `active` or `reversed`.
 * @property-read int $points_redeemed
 * @property-read string|null $monetary_value Two decimals, e.g. `"1.00"`.
 * @property-read string|null $currency
 * @property-read QuoyerObject|null $reward v1.11: the catalogue reward as redeemed (`id`, `name`, `type`, `points_cost`, `amount`, `currency`, `percent`, `product_id`); null for points as money off.
 * @property-read string|null $coupon_code `QYR-XXXXXXXX`: a reference. Create the real coupon on your shop.
 * @property-read list<QuoyerObject> $bucket_consumption `bucket_id` and `amount` per drained bucket.
 * @property-read string|null $source_reference
 * @property-read QuoyerObject|array<mixed> $metadata
 * @property-read string|null $reversed_at
 * @property-read string $created_at
 * @property-read string $updated_at
 * @property-read bool|null $was_new From create(): false on a replay.
 * @property-read bool|null $was_newly_reversed From reverse(): false when it was already reversed.
 * @property-read int|null $points_restored From reverse(): 0 on a replay.
 * @property-read list<QuoyerObject>|null $bucket_restorations From reverse(): `bucket_id`, `amount`, `expired`.
 */
final class Redemption extends QuoyerObject
{
    public function isReversed(): bool
    {
        return $this->stringOrNull('status') === 'reversed';
    }

    /**
     * From create(): false when this source_reference had been redeemed
     * before and the original redemption is returned.
     */
    public function wasNew(): bool
    {
        return $this->bool('was_new');
    }

    /**
     * From reverse(): false when it had already been reversed.
     */
    public function wasNewlyReversed(): bool
    {
        return $this->bool('was_newly_reversed');
    }
}
