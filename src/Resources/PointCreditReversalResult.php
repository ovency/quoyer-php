<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * The answer to a credit reversal. A repeated reversal is a success too,
 * with `points_reversed: 0` and `was_newly_reversed: false`.
 *
 * @property-read string $object `point_credit_reversal_result`
 * @property-read int $points_withdrawn Points taken back from the first bucket by THIS call.
 * @property-read bool $was_newly_reversed Whether THIS call reversed the first bucket.
 * @property-read PointBucket $bucket
 * @property-read int $points_reversed Points taken back across every bucket by THIS call. 0 on a replay.
 * @property-read list<PointBucket> $buckets Every bucket the credit produced, all now reversed.
 */
final class PointCreditReversalResult extends QuoyerObject
{
    public function pointsReversed(): int
    {
        return $this->int('points_reversed');
    }

    /**
     * False when the credit had already been reversed (HTTP 409, which the
     * SDK treats as the success it is).
     */
    public function wasNewlyReversed(): bool
    {
        return $this->bool('was_newly_reversed');
    }
}
