<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * The answer to a credit.
 *
 * @property-read string $object `point_credit_result`
 * @property-read int $points_awarded The total across every bucket.
 * @property-read bool $was_new False on a replay of the same source_reference.
 * @property-read PointBucket|null $bucket The base rule's bucket. Null when nothing was awarded.
 * @property-read list<PointBucket> $buckets Every bucket this credit produced, base first, then each stacking bonus.
 * @property-read string|null $note Only on a zero-point result, e.g. below the rule's minimum amount.
 */
final class PointCreditResult extends QuoyerObject
{
    public function pointsAwarded(): int
    {
        return $this->int('points_awarded');
    }

    /**
     * False when this source_reference had been credited before: the
     * original result is returned and nothing new was awarded.
     */
    public function wasNew(): bool
    {
        return $this->bool('was_new');
    }
}
