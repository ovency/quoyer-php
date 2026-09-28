<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * One entry in a customer's wallet ledger, the source of truth for the balance.
 *
 * @property-read string $id Numeric, as a string.
 * @property-read string $object `point_transaction`
 * @property-read string $type `credit` or `debit`.
 * @property-read string $kind `earn`, `spend`, `deduction`, `refund`, `expiry`, `clawback` or `adjustment`: label an activity feed with it.
 * @property-read int $amount Always positive.
 * @property-read int $amount_signed Negative for debits.
 * @property-read string|null $description A label for staff. Do not parse it.
 * @property-read string|null $bucket_id `bkt_…` for earns, expiries and clawbacks.
 * @property-read string|null $redemption_record_id `red_…` for spends, deductions and refunds.
 * @property-read bool $is_reversed True on an `earn` whose credit was later clawed back.
 * @property-read string|null $expired_at On `expiry` rows.
 * @property-read string $created_at
 */
final class PointTransaction extends QuoyerObject {}
