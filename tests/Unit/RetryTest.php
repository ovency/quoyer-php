<?php

declare(strict_types=1);

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Quoyer\Exceptions\ConfigurationException;
use Quoyer\Exceptions\ConnectionException;
use Quoyer\Exceptions\RateLimitException;
use Quoyer\Tests\Support\Fixtures;

function unavailable(): Response
{
    return jsonResponse(503, Fixtures::error('configuration_error', 'account_state_unknown'));
}

function connectionRefused(): ConnectException
{
    return new ConnectException('Connection refused', new Request('GET', 'https://quoyer.example'));
}

it('retries a read after a 503 with growing backoff', function () {
    $http = fakeHttp(unavailable(), unavailable(), jsonResponse(200, Fixtures::customer()));
    $customer = $http->client()->customers->retrieve('cus_1');

    expect($customer->id)->toBe('cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX')
        ->and($http->sentCount())->toBe(3)
        ->and($http->sleeps)->toHaveCount(2)
        ->and($http->sleeps[0])->toBeBetween(375, 625)
        ->and($http->sleeps[1])->toBeBetween(750, 1250);
});

it('retries after a connection error', function () {
    $http = fakeHttp(connectionRefused(), jsonResponse(200, Fixtures::customer()));

    expect($http->client()->customers->retrieve('cus_1')->id)->not->toBeNull()
        ->and($http->sentCount())->toBe(2);
});

it('gives up after max_retries with the last error', function () {
    $http = fakeHttp(unavailable(), unavailable(), unavailable());

    expect(fn () => $http->client()->program->retrieve())->toThrow(ConfigurationException::class)
        ->and($http->sentCount())->toBe(3);
});

it('throws ConnectionException when Quoyer stays unreachable', function () {
    $http = fakeHttp(connectionRefused(), connectionRefused(), connectionRefused());

    expect(fn () => $http->client()->program->retrieve())->toThrow(ConnectionException::class, 'Could not reach Quoyer');
});

it('retries idempotent writes: a repeated credit returns the original', function () {
    $http = fakeHttp(unavailable(), jsonResponse(200, Fixtures::creditResult(wasNew: false)));

    expect($http->client()->points->credit(['customer_id' => 'cus_1', 'rule_type' => 'purchase', 'source_reference' => 'o_1'])->wasNew())->toBeFalse()
        ->and($http->sentCount())->toBe(2);
});

it('never retries a write that could happen twice', function (Closure $call) {
    $http = fakeHttp(unavailable(), jsonResponse(201, ['object' => 'x']));

    expect(fn () => $call($http->client()))->toThrow(ConfigurationException::class)
        ->and($http->sentCount())->toBe(1);
})->with([
    'create an earning rule' => [fn ($client) => $client->earningRules->create(['name' => 'n', 'type' => 'signup', 'fixed_points' => 10])],
    'create a currency' => [fn ($client) => $client->currencies->create(['currency_code' => 'CHF', 'currency_units_per_point' => 100])],
    'delete a customer' => [fn ($client) => $client->customers->delete('cus_1')],
    'upsert with a referral code' => [fn ($client) => $client->customers->upsert(['email' => 'a@b.c', 'referral_code' => 'K7M2QX9P'])],
]);

it('waits out a short 429 even on a non-idempotent call, because nothing was done', function () {
    $http = fakeHttp(
        jsonResponse(429, Fixtures::error('rate_limit_error', 'too_many_requests'), ['Retry-After' => '2']),
        jsonResponse(201, ['id' => 'rul_1', 'object' => 'earning_rule']),
    );

    $http->client()->earningRules->create(['name' => 'n', 'type' => 'signup', 'fixed_points' => 10]);

    expect($http->sentCount())->toBe(2)
        ->and($http->sleeps)->toBe([2000]);
});

it('does not hold a request through a long 429', function () {
    $http = fakeHttp(jsonResponse(429, Fixtures::error('rate_limit_error', 'too_many_requests'), ['Retry-After' => '42']));

    expect(fn () => $http->client()->program->retrieve())->toThrow(RateLimitException::class)
        ->and($http->sleeps)->toBe([]);
});

it('does not retry at all with max_retries 0', function () {
    $http = fakeHttp(unavailable());

    expect(fn () => $http->client(['max_retries' => 0])->program->retrieve())->toThrow(ConfigurationException::class)
        ->and($http->sleeps)->toBe([]);
});
