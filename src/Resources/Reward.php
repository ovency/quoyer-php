<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * One reward in the merchant's catalogue (API contract v1.11).
 *
 * @property-read string $id `rwd_…`
 * @property-read string $object `reward`
 * @property-read string $name Shopper-facing.
 * @property-read string|null $description Shopper-facing conditions.
 * @property-read string $summary The reward in one sentence for a shopper, from its own data: "Spend 500 points, get an extra 10 RON off." In the language of the `locale` parameter, else the named customer's, else English (v1.17).
 * @property-read string $type `money_off`, `percent_off`, `free_product` or `free_shipping`.
 * @property-read int $points_cost 0 is a coupon (v1.12): given to customers, listed only for a holder, one redemption per coupon.
 * @property-read string|null $amount `money_off`: two decimals.
 * @property-read int|null $amount_minor_units `money_off`.
 * @property-read string|null $currency `money_off`.
 * @property-read int|null $percent `percent_off`: 1-100.
 * @property-read string|null $product_id `free_product`: YOUR product id, when set.
 * @property-read int|null $per_customer_limit Active redemptions one customer may hold.
 * @property-read string|null $starts_at
 * @property-read string|null $ends_at
 * @property-read bool $is_available
 * @property-read QuoyerObject|null $customer With a customer: `can_redeem`, `reason` (incl. `not_issued` for a coupon they hold none of), `points_short`, `held`, `coupons` (v1.12).
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Reward extends QuoyerObject
{
    public const MONEY_OFF = 'money_off';

    public const PERCENT_OFF = 'percent_off';

    public const FREE_PRODUCT = 'free_product';

    public const FREE_SHIPPING = 'free_shipping';

    /**
     * Whether the customer the list was asked for can redeem it now. False
     * when the list was not asked for a customer.
     */
    public function canRedeemFor(): bool
    {
        $customer = $this->raw['customer'] ?? null;

        return is_array($customer) && ($customer['can_redeem'] ?? false) === true;
    }
}
