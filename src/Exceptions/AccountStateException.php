<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

/**
 * 403 `account_state_error`: the merchant's account refuses this operation
 * (`redemption_unavailable`, `account_read_only`, `account_pending`,
 * `program_closed`, `program_paused`, `store_limit_reached`).
 *
 * getMessage() is safe to show a shopper. getAdminReason() is the real
 * reason, for the merchant and logs only. Balances are never affected, and
 * earning keeps working while an account is suspended.
 */
class AccountStateException extends ApiException {}
