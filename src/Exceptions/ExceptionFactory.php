<?php

declare(strict_types=1);

namespace Quoyer\Exceptions;

use Quoyer\Http\ApiResponse;

/**
 * Maps an error response to the exception class that fits it.
 *
 * @internal
 */
final class ExceptionFactory
{
    public static function fromResponse(ApiResponse $response): ApiException|UnexpectedResponseException
    {
        $error = $response->json['error'] ?? null;

        if (! is_array($error) || ! is_string($error['code'] ?? null)) {
            return new UnexpectedResponseException(
                sprintf(
                    'Quoyer answered HTTP %d without an error envelope. Check base_url; body starts: %s',
                    $response->statusCode,
                    substr(trim($response->body), 0, 200),
                ),
                $response,
            );
        }

        $type = is_string($error['type'] ?? null) ? $error['type'] : 'unknown';
        $code = $error['code'];
        $status = $response->statusCode;

        $class = match (true) {
            $status === 429 || $type === 'rate_limit_error' => RateLimitException::class,
            $type === 'authentication_error' => AuthenticationException::class,
            $type === 'account_state_error' => AccountStateException::class,
            $status === 404 => NotFoundException::class,
            $type === 'validation_error' => ValidationException::class,
            $type === 'configuration_error' => ConfigurationException::class,
            default => InvalidRequestException::class,
        };

        return new $class(
            message: is_string($error['message'] ?? null) ? $error['message'] : $code,
            httpStatus: $status,
            errorType: $type,
            errorCode: $code,
            field: is_string($error['field'] ?? null) ? $error['field'] : null,
            errors: self::errors($error['errors'] ?? null),
            adminReason: is_string($error['admin_reason'] ?? null) ? $error['admin_reason'] : null,
            response: $response,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    private static function errors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $clean = [];
        foreach ($errors as $field => $messages) {
            $clean[(string) $field] = array_values(array_map('strval', array_filter((array) $messages, 'is_scalar')));
        }

        return $clean;
    }
}
