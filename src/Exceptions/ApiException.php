<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

use Quoyer\Http\ApiResponse;
use RuntimeException;

/**
 * The API answered with an error envelope:
 *
 *     {"error": {"type": "…", "code": "…", "message": "…", "field": "…", "errors": {…}, "admin_reason": "…"}}
 *
 * **Branch on getErrorCode()**, never on the message: codes are stable,
 * messages can change. The ErrorCode class lists every code the API sends.
 *
 * getMessage() is the API's message. It is shopper-safe only when
 * isShopperSafe() says so; otherwise treat it as a log line.
 * getAdminReason() is for the merchant and your logs, never for a shopper.
 */
class ApiException extends RuntimeException implements QuoyerException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    final public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly string $errorType,
        private readonly string $errorCode,
        private readonly ?string $field = null,
        private readonly array $errors = [],
        private readonly ?string $adminReason = null,
        private readonly ?ApiResponse $response = null,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * The broad category, e.g. `invalid_request`. Prefer getErrorCode().
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * The stable, machine-readable code, e.g. `insufficient_points`. See ErrorCode.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * The first failing input field, when there is one.
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * Validation errors: every failing field and its messages.
     *
     * @return array<string, list<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Why an account-state refusal or a member cap happened. For the merchant
     * and logs only: **never show it to a shopper.**
     */
    public function getAdminReason(): ?string
    {
        return $this->adminReason;
    }

    public function getResponse(): ?ApiResponse
    {
        return $this->response;
    }

    /**
     * Whether getMessage() is written to be shown to a shopper. True for the
     * account-state refusals (including a paused programme) and for a
     * feature the plan does not include.
     */
    public function isShopperSafe(): bool
    {
        return $this->errorType === 'account_state_error' || $this->errorCode === 'feature_not_in_plan';
    }
}
