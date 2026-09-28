<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

use RuntimeException;

/**
 * A webhook delivery failed verification: a missing or malformed
 * `X-Quoyer-Signature`, a wrong signature, or a timestamp outside the
 * tolerance. Answer 400 and do not process it.
 */
class SignatureVerificationException extends RuntimeException implements QuoyerException {}
