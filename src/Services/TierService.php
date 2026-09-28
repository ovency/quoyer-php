<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Quoyer\Resources\TierList;

/**
 * The VIP ladder: `$quoyer->tiers`.
 */
final class TierService extends AbstractService
{
    /**
     * The tiers, lowest first, and whether they apply now. When
     * `isActive()` is false, show nothing about tiers.
     */
    public function list(): TierList
    {
        return $this->listing('/tiers', class: TierList::class);
    }
}
