<?php

declare(strict_types=1);

namespace Quoyer\Enums;

/**
 * The `status` filter on bucket lists.
 */
enum BucketStatus: string
{
    /** Points remain and have not expired: the spendable buckets. */
    case Active = 'active';

    /** Past `expires_at`, whatever remains. */
    case Expired = 'expired';

    /** Fully spent, not expired, not reversed. */
    case Consumed = 'consumed';

    /** Clawed back by a credit reversal. */
    case Reversed = 'reversed';

    /** Nothing remains, for any reason. */
    case Empty = 'empty';
}
