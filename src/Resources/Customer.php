<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * A loyalty member.
 *
 * Identified by Quoyer's `cus_` id, or by your own id plus a source name
 * (`external_id`, `external_source`), e.g. `("1042", "woocommerce")`.
 *
 * @property-read string $id `cus_…`
 * @property-read string $object `customer`
 * @property-read string|null $external_id Your id for this customer.
 * @property-read string|null $external_source Your platform's name.
 * @property-read string|null $email Null for a customer who joined with a phone.
 * @property-read string|null $phone E.164, e.g. `+40722123456`.
 * @property-read string|null $card_number The Quoyer card number: ten digits, Luhn-checked. Not a secret.
 * @property-read string|null $first_name
 * @property-read string|null $last_name
 * @property-read string|null $birthday `YYYY-MM-DD`.
 * @property-read QuoyerObject $balance `points` (int) and `value` (null, or `amount_minor_units`, `amount`, `currency`).
 * @property-read string|null $last_activity_at When the customer last earned or redeemed.
 * @property-read QuoyerObject|null $tier `id`, `name`, `badge_color`, `multiplier`, `since`. Null when VIP tiers are not active.
 * @property-read QuoyerObject|null $tier_progress `qualifying_points`, `next_tier`, `points_needed`. Null when VIP tiers are not active.
 * @property-read string|null $referral_code The member's own code to share. Null while referrals are off.
 * @property-read string|null $locale BCP-47, e.g. `ro`, `en-GB`.
 * @property-read bool $email_opt_out
 * @property-read QuoyerObject|null $lifetime `earned_points`, `redeemed_points`, `currency`. Only from retrieve() and balance().
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Customer extends QuoyerObject
{
    /**
     * The spendable balance in points.
     */
    public function points(): int
    {
        $balance = $this->values['balance'] ?? null;

        return $balance instanceof QuoyerObject && is_int($balance->points) ? $balance->points : 0;
    }

    /**
     * Whether upsert() created this customer (201) rather than updating one (200).
     */
    public function wasCreated(): bool
    {
        return $this->getLastResponse()?->statusCode === 201;
    }
}
