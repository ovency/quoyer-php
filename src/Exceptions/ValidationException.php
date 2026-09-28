<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * 422 `validation_error`: a field failed validation (`invalid_request`) or
 * was removed from the API (`field_removed`). getErrors() maps every
 * failing field to its messages.
 */
class ValidationException extends InvalidRequestException {}
