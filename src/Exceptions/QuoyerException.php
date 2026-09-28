<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

use Throwable;

/**
 * Implemented by every exception the SDK throws. Catch this to catch them all.
 */
interface QuoyerException extends Throwable {}
