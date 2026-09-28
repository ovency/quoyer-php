<?php

declare(strict_types=1);

namespace Quoyer;

/**
 * Every error code the Quoyer API sends (contract v1.9), for comparing with
 * ApiException::getErrorCode(). Codes are stable; new ones can be added, so
 * always keep a default branch.
 *
 *     catch (InvalidRequestException $e) {
 *         if ($e->getErrorCode() === ErrorCode::INSUFFICIENT_POINTS) { … }
 *     }
 */
final class ErrorCode
{
    // Authentication (401)
    public const MISSING_CREDENTIALS = 'missing_credentials';

    public const INVALID_CREDENTIALS = 'invalid_credentials';

    public const REVOKED_CREDENTIALS = 'revoked_credentials';

    public const TENANT_NOT_FOUND = 'tenant_not_found';

    public const TENANT_NOT_IDENTIFIED = 'tenant_not_identified';

    // Account state (403): the message is shopper-safe, admin_reason is not
    public const REDEMPTION_UNAVAILABLE = 'redemption_unavailable';

    public const ACCOUNT_READ_ONLY = 'account_read_only';

    public const ACCOUNT_PENDING = 'account_pending';

    public const PROGRAM_CLOSED = 'program_closed';

    public const PROGRAM_PAUSED = 'program_paused';

    public const STORE_LIMIT_REACHED = 'store_limit_reached';

    public const FEATURE_NOT_IN_PLAN = 'feature_not_in_plan';

    // Rate limiting (429)
    public const TOO_MANY_REQUESTS = 'too_many_requests';

    // Validation (422)
    public const INVALID_REQUEST = 'invalid_request';

    public const FIELD_REMOVED = 'field_removed';

    // Not found (404)
    public const RESOURCE_NOT_FOUND = 'resource_not_found';

    public const CUSTOMER_NOT_FOUND = 'customer_not_found';

    public const BUCKET_NOT_FOUND = 'bucket_not_found';

    // Customers
    public const EMAIL_CONFLICT = 'email_conflict';

    public const PHONE_CONFLICT = 'phone_conflict';

    public const EXTERNAL_ID_CONFLICT = 'external_id_conflict';

    public const MEMBER_LIMIT_REACHED = 'member_limit_reached';

    // Earning
    public const LOCATION_NOT_FOUND = 'location_not_found';

    public const RULE_REQUIRED = 'rule_required';

    public const RULE_NOT_FOUND = 'rule_not_found';

    public const INVALID_RULE_TYPE = 'invalid_rule_type';

    public const NO_ACTIVE_RULE = 'no_active_rule';

    public const INVALID_EARN_REQUEST = 'invalid_earn_request';

    public const UNCONFIGURED_CURRENCY = 'unconfigured_currency';

    public const DUPLICATE_SOURCE_REFERENCE = 'duplicate_source_reference';

    public const INVALID_CREDIT_REVERSE_REQUEST = 'invalid_credit_reverse_request';

    // Redemptions
    public const INSUFFICIENT_POINTS = 'insufficient_points';

    public const INVALID_REDEEM_REQUEST = 'invalid_redeem_request';

    public const INVALID_REVERSE_REQUEST = 'invalid_reverse_request';

    // Currencies and programme
    public const CURRENCY_ALREADY_EXISTS = 'currency_already_exists';

    public const INVALID_CURRENCY_CODE = 'invalid_currency_code';

    public const PROGRAM_NOT_INITIALIZED = 'program_not_initialized';

    // Quoyer-side configuration (503)
    public const ACCOUNT_STATE_UNKNOWN = 'account_state_unknown';

    public const OPERATION_NOT_CLASSIFIED = 'operation_not_classified';
}
