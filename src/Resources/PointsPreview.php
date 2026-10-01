<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * What an order would earn (v1.15): the answer to `points->preview()`.
 *
 * @property-read string $object `points_preview`
 * @property-read int $points The number to show: `base_points` + `tier_bonus_points`.
 * @property-read int $base_points From the purchase rules, as a credit would award them.
 * @property-read int $tier_bonus_points The tier multiplier's extra points; 0 without a named customer or an active tier.
 * @property-read string $currency ISO 4217: the currency the amount was read in.
 * @property-read QuoyerObject|null $tier The named customer's tier (`id`, `name`, `multiplier`, `purchase_discount_percent`), when tiers are active.
 * @property-read bool $program_active False while the programme is paused: `points` is then 0.
 */
final class PointsPreview extends QuoyerObject
{
    public function points(): int
    {
        return $this->int('points');
    }
}
