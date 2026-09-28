<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Quoyer could not be reached: DNS, TLS, a refused connection or a timeout.
 * Nothing is known about whether a write happened, so repeat it with the
 * same `source_reference`: the API returns the original result.
 */
class ConnectionException extends RuntimeException implements QuoyerException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
