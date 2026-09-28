<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * `configuration_error`: something is not set up, on the merchant's side
 * (`unconfigured_currency`) or Quoyer's (`account_state_unknown`,
 * `operation_not_classified`, `program_not_initialized`).
 */
class ConfigurationException extends ApiException {}
