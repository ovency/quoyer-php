<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * The request cannot be carried out as sent (`invalid_request` type):
 * `insufficient_points`, `email_conflict`, `member_limit_reached`,
 * `no_active_rule`, `feature_not_in_plan` and the rest. Read
 * getErrorCode(); do not retry unchanged.
 */
class InvalidRequestException extends ApiException {}
