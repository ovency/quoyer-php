<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * One rung of the VIP ladder.
 *
 * @property-read string $id `tir_…`
 * @property-read string $object `tier`
 * @property-read string $name
 * @property-read int $threshold Qualifying points (earned in 365 days) to reach it. The lowest tier is 0.
 * @property-read int $multiplier Purchase earning in hundredths: 100 normal, 150 = 1.5×.
 * @property-read string $badge_color
 * @property-read int $sort_order
 * @property-read string|null $created_at
 * @property-read string|null $updated_at
 */
final class Tier extends QuoyerObject {}
