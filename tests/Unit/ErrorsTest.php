<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Quoyer\ErrorCode;
use Quoyer\Exceptions\AccountStateException;
use Quoyer\Exceptions\ApiException;
use Quoyer\Exceptions\AuthenticationException;
use Quoyer\Exceptions\ConfigurationException;
use Quoyer\Exceptions\InvalidRequestException;
use Quoyer\Exceptions\NotFoundException;
use Quoyer\Exceptions\QuoyerException;
use Quoyer\Exceptions\RateLimitException;
use Quoyer\Exceptions\UnexpectedResponseException;
use Quoyer\Exceptions\ValidationException;
use Quoyer\Tests\Support\Fixtures;

it('maps every error to the class that fits it', function (int $status, string $type, string $code, string $class) {
    $http = fakeHttp(jsonResponse($status, Fixtures::error($type, $code)));

    try {
        $http->client(['max_retries' => 0])->program->retrieve();
        $this->fail('Expected an exception');
    } catch (ApiException $e) {
        expect($e)->toBeInstanceOf($class)
            ->and($e)->toBeInstanceOf(QuoyerException::class)
            ->and($e->getErrorCode())->toBe($code)
            ->and($e->getErrorType())->toBe($type)
            ->and($e->getHttpStatus())->toBe($status);
    }
})->with([
    [401, 'authentication_error', ErrorCode::MISSING_CREDENTIALS, AuthenticationException::class],
    [401, 'authentication_error', ErrorCode::REVOKED_CREDENTIALS, AuthenticationException::class],
    [403, 'account_state_error', ErrorCode::ACCOUNT_READ_ONLY, AccountStateException::class],
    [403, 'account_state_error', ErrorCode::PROGRAM_PAUSED, AccountStateException::class],
    [403, 'account_state_error', ErrorCode::STORE_LIMIT_REACHED, AccountStateException::class],
    [403, 'invalid_request', ErrorCode::FEATURE_NOT_IN_PLAN, InvalidRequestException::class],
    [404, 'not_found', ErrorCode::RESOURCE_NOT_FOUND, NotFoundException::class],
    [404, 'invalid_request', ErrorCode::CUSTOMER_NOT_FOUND, NotFoundException::class],
    [409, 'invalid_request', ErrorCode::CURRENCY_ALREADY_EXISTS, InvalidRequestException::class],
    [422, 'validation_error', ErrorCode::INVALID_REQUEST, ValidationException::class],
    [422, 'validation_error', ErrorCode::FIELD_REMOVED, ValidationException::class],
    [422, 'invalid_request', ErrorCode::INSUFFICIENT_POINTS, InvalidRequestException::class],
    [422, 'invalid_request', ErrorCode::MEMBER_LIMIT_REACHED, InvalidRequestException::class],
    [422, 'configuration_error', ErrorCode::UNCONFIGURED_CURRENCY, ConfigurationException::class],
    [429, 'rate_limit_error', ErrorCode::TOO_MANY_REQUESTS, RateLimitException::class],
    [500, 'configuration_error', ErrorCode::PROGRAM_NOT_INITIALIZED, ConfigurationException::class],
    [503, 'configuration_error', ErrorCode::ACCOUNT_STATE_UNKNOWN, ConfigurationException::class],
]);

it('keeps a code it has never heard of', function () {
    $http = fakeHttp(jsonResponse(422, Fixtures::error('invalid_request', 'brand_new_code', 'A new rule.')));

    expect(fn () => $http->client()->program->retrieve())
        ->toThrow(fn (InvalidRequestException $e) => expect($e->getErrorCode())->toBe('brand_new_code'));
});

it('carries validation details', function () {
    $http = fakeHttp(jsonResponse(422, Fixtures::error('validation_error', 'invalid_request', 'The email field must be a valid email address.', [
        'field' => 'email',
        'errors' => ['email' => ['The email field must be a valid email address.']],
    ])));

    try {
        $http->client()->customers->upsert(['email' => 'not-an-email']);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->getField())->toBe('email')
            ->and($e->getErrors())->toBe(['email' => ['The email field must be a valid email address.']])
            ->and($e->isShopperSafe())->toBeFalse();
    }
});

it('says which messages are safe to show a shopper', function (string $type, string $code, bool $safe) {
    $http = fakeHttp(jsonResponse(403, Fixtures::error($type, $code)));

    try {
        $http->client()->program->retrieve();
    } catch (ApiException $e) {
        expect($e->isShopperSafe())->toBe($safe);
    }
})->with([
    ['account_state_error', ErrorCode::REDEMPTION_UNAVAILABLE, true],
    ['account_state_error', ErrorCode::PROGRAM_PAUSED, true],
    ['invalid_request', ErrorCode::FEATURE_NOT_IN_PLAN, true],
    ['invalid_request', ErrorCode::INSUFFICIENT_POINTS, false],
]);

it('reads the wait from a rate-limit refusal', function () {
    $http = fakeHttp(jsonResponse(429, Fixtures::error('rate_limit_error', 'too_many_requests'), ['Retry-After' => '42']));

    try {
        $http->client()->program->retrieve();
        $this->fail('Expected RateLimitException');
    } catch (RateLimitException $e) {
        expect($e->getRetryAfter())->toBe(42);
    }
});

it('explains a response that is not the API\'s', function () {
    $http = fakeHttp(new Response(500, ['Content-Type' => 'text/html'], '<html>Server Error</html>'));

    expect(fn () => $http->client(['max_retries' => 0])->program->retrieve())
        ->toThrow(UnexpectedResponseException::class, 'without an error envelope');
});

it('refuses to follow a redirect, which usually means a wrong base URL', function () {
    $http = fakeHttp(new Response(301, ['Location' => 'https://quoyer.com/api/v1/program']));

    expect(fn () => $http->client()->program->retrieve())
        ->toThrow(UnexpectedResponseException::class, 'redirect');
});

it('exposes the rate limit and warning headers of a success', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::customer(), [
        'X-RateLimit-Limit' => '300',
        'X-RateLimit-Remaining' => '299',
        'X-RateLimit-Tier' => 'read',
        'X-Quoyer-Warning' => 'payment_past_due',
    ]));

    $response = $http->client()->customers->retrieve('cus_1')->getLastResponse();

    expect($response->rateLimit()->limit)->toBe(300)
        ->and($response->rateLimit()->remaining)->toBe(299)
        ->and($response->rateLimit()->tier)->toBe('read')
        ->and($response->isPaymentPastDue())->toBeTrue();
});
