<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * One batch of points credited to a customer: the rule that awarded it,
 * what is left, and when it expires.
 *
 * @property-read string $id `bkt_…`
 * @property-read string $object `point_bucket`
 * @property-read string|null $customer_id `cus_…`
 * @property-read QuoyerObject|null $earning_rule `id`, `type`, `name` (internal) and `label` (the rule in a shopper's words, v1.16).
 * @property-read string $label What these points were for, in a shopper's words and language (v1.16). Show this, not the rule's name.
 * @property-read int $original_amount
 * @property-read int $remaining_amount
 * @property-read int $consumed_amount 0 when the bucket was reversed.
 * @property-read bool $is_active Points remain, not expired and not reversed.
 * @property-read bool $is_expired
 * @property-read bool $is_fully_consumed Spent to zero. A reversal also zeroes remaining_amount: check is_reversed.
 * @property-read bool $is_reversed Clawed back (refund, cancellation). Count none of it as earned.
 * @property-read string|null $reversed_at
 * @property-read string|null $reversed_reason
 * @property-read QuoyerObject|null $reversed_by
 * @property-read string $issued_at
 * @property-read string|null $expires_at Null if the points never expire.
 * @property-read string|null $expired_at
 * @property-read QuoyerObject|array<mixed> $metadata What the credit carried, plus Quoyer's audit keys.
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class PointBucket extends QuoyerObject {}
