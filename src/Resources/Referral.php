<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * One use of a member's referral code.
 *
 * @property-read string $id `ref_…`
 * @property-read string $object `referral`
 * @property-read string|null $referrer_id `cus_…`; null for an unknown code.
 * @property-read string|null $friend_id
 * @property-read string $code
 * @property-read string $status `pending`, `rewarded`, `reversed` or `rejected`.
 * @property-read string|null $reason Why it was rejected, or why the referrer was not paid.
 * @property-read string|null $first_order_reference
 * @property-read bool $referrer_rewarded
 * @property-read int|null $referrer_points
 * @property-read int|null $friend_points
 * @property-read string|null $captured_at
 * @property-read string|null $rewarded_at
 * @property-read string|null $reversed_at
 */
final class Referral extends QuoyerObject {}
