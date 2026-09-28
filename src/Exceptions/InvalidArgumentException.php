<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * The SDK was called wrongly (a missing API key, an empty id, a missing
 * `source_reference`). Thrown before any request is sent.
 */
class InvalidArgumentException extends \InvalidArgumentException implements QuoyerException {}
