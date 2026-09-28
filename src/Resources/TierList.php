<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\Collection;

/**
 * The VIP ladder, lowest first (at most five tiers, never paged).
 *
 * @extends Collection<Tier>
 *
 * @property-read bool $active Whether tiers apply now. When false, show nothing about tiers.
 */
final class TierList extends Collection
{
    public function isActive(): bool
    {
        return $this->bool('active');
    }
}
