<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * The programme's settings. One per merchant.
 *
 * @property-read string $object `program`
 * @property-read string $name
 * @property-read bool $is_active The merchant's switch. When false, credits and redemptions answer `program_paused`.
 * @property-read string|null $default_currency
 * @property-read int|null $default_expiry_days Null = never.
 * @property-read int $redemption_return_bp A point's worth as a share of the spend that earned it, in basis points.
 * @property-read int|null $minimum_redemption_points
 * @property-read int|null $maximum_redemption_points
 * @property-read list<QuoyerObject> $currencies The active currencies: `currency_code`, `currency_units_per_point`, `point_value_minor_units`, `is_default`.
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Program extends QuoyerObject {}
