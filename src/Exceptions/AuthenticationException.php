<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * 401: no key, an unknown or revoked key, or an orphaned account
 * (`missing_credentials`, `invalid_credentials`, `revoked_credentials`,
 * `tenant_not_found`, `tenant_not_identified`). Stop calling and ask the
 * merchant for a valid key; retrying will not help.
 */
class AuthenticationException extends ApiException {}
