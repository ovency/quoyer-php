<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * The earning rate for one currency.
 *
 * @property-read string $id The ISO 4217 code.
 * @property-read string $object `currency`
 * @property-read string $currency_code
 * @property-read int $currency_units_per_point Minor units spent to earn one point. 100 means €1.00 earns 1 point.
 * @property-read int $point_value_minor_units What one point is worth at redemption. Read-only, derived.
 * @property-read bool $is_active
 * @property-read bool $is_default
 * @property-read string|null $notes
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Currency extends QuoyerObject {}
