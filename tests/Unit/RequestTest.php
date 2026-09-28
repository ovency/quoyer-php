<?php

declare(strict_types=1);

use Quoyer\Enums\BucketStatus;
use Quoyer\Enums\RuleType;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Tests\Support\Fixtures;

it('authenticates with a bearer key and asks for JSON', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::me()));
    $http->client()->me();

    $request = $http->request();
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer quoy_sandbox_abc123.secretsecretsecret')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('User-Agent'))->toStartWith('quoyer-php/')
        ->and((string) $request->getUri())->toBe('https://quoyer.example/api/v1/me');
});

it('sends X-Quoyer-Store only when a store URL is set', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::me()), jsonResponse(200, Fixtures::me()));

    $http->client()->me();
    $http->client(['store_url' => 'https://shop.example.com'])->me();

    expect($http->request(0)->hasHeader('X-Quoyer-Store'))->toBeFalse()
        ->and($http->request(1)->getHeaderLine('X-Quoyer-Store'))->toBe('https://shop.example.com');
});

it('encodes query parameters: booleans as words, enums as values, dates as ISO 8601, nulls dropped', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::list()), jsonResponse(200, Fixtures::list()));
    $client = $http->client();

    $client->earningRules->list(['type' => RuleType::Purchase, 'include_inactive' => true, 'currently_active' => false, 'cursor' => null]);
    $client->buckets->list([
        'status' => BucketStatus::Active,
        'expires_before' => new DateTimeImmutable('2027-01-31T00:00:00+00:00'),
    ]);

    expect($http->query(0))->toBe(['type' => 'purchase', 'include_inactive' => 'true', 'currently_active' => 'false'])
        ->and($http->query(1))->toBe(['status' => 'active', 'expires_before' => '2027-01-31T00:00:00+00:00']);
});

it('sends JSON bodies with enums and dates converted', function () {
    $http = fakeHttp(jsonResponse(201, Fixtures::creditResult()));

    $http->client()->points->credit([
        'customer_external_id' => '1042',
        'customer_external_source' => 'myshop',
        'rule_type' => RuleType::Purchase,
        'source_reference' => 'myshop_order_5531',
        'metadata' => ['amount' => 49.99, 'currency' => 'EUR', 'line_items' => [['product_id' => '77', 'amount' => 40.0]]],
        'occurred_at' => new DateTimeImmutable('2026-09-26T22:10:34+00:00'),
    ]);

    $request = $http->request();
    expect($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and($http->body())->toBe([
            'customer_external_id' => '1042',
            'customer_external_source' => 'myshop',
            'rule_type' => 'purchase',
            'source_reference' => 'myshop_order_5531',
            'metadata' => ['amount' => 49.99, 'currency' => 'EUR', 'line_items' => [['product_id' => '77', 'amount' => 40.0]]],
            'occurred_at' => '2026-09-26T22:10:34+00:00',
        ]);
});

it('sends an empty body as an object, never an array', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::redemption(['status' => 'reversed'])));
    $http->client()->redemptions->reverse('red_1');

    expect((string) $http->request()->getBody())->toBe('{}');
});

it('encodes ids in paths', function () {
    $http = fakeHttp(jsonResponse(200, ['id' => 'x', 'object' => 'currency']));
    $http->client()->currencies->retrieve('EU R/..');

    expect($http->request()->getUri()->getPath())->toBe('/api/v1/currencies/EU%20R%2F..');
});

it('refuses an empty id before sending anything', function () {
    $http = fakeHttp();
    $http->client()->customers->retrieve(' ');
})->throws(InvalidArgumentException::class, 'customer id');

it('calls any endpoint through request(), for routes newer than the SDK', function () {
    $http = fakeHttp(jsonResponse(200, ['object' => 'list', 'data' => []]));
    $response = $http->client()->request('get', '/locations', ['limit' => 5]);

    expect($http->route())->toBe('GET /locations')
        ->and($http->query())->toBe(['limit' => '5'])
        ->and($response->json)->toBe(['object' => 'list', 'data' => []]);
});

it('refuses a request() path without a leading slash', function () {
    fakeHttp()->client()->request('GET', 'customers');
})->throws(InvalidArgumentException::class);
