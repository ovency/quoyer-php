<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Quoyer\Collection;
use Quoyer\Resources\Customer;
use Quoyer\Resources\PointBucket;
use Quoyer\Resources\PointTransaction;
use Quoyer\Resources\Referral;
use Quoyer\Tests\Support\Fixtures;

it('upserts a customer and tells a new one from an update', function () {
    $http = fakeHttp(jsonResponse(201, Fixtures::customer()), jsonResponse(200, Fixtures::customer()));
    $client = $http->client();

    $created = $client->customers->upsert(['external_id' => '1042', 'external_source' => 'woocommerce', 'email' => 'jane@example.com']);
    $updated = $client->customers->upsert(['external_id' => '1042', 'external_source' => 'woocommerce', 'first_name' => 'Jane']);

    expect($http->route())->toBe('POST /customers')
        ->and($http->body())->toBe(['external_id' => '1042', 'external_source' => 'woocommerce', 'email' => 'jane@example.com'])
        ->and($created)->toBeInstanceOf(Customer::class)
        ->and($created->wasCreated())->toBeTrue()
        ->and($updated->wasCreated())->toBeFalse();
});

it('retrieves a customer with nested balance, tier and lifetime', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::customer()));
    $customer = $http->client()->customers->retrieve('cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX');

    expect($http->route())->toBe('GET /customers/cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX')
        ->and($customer->id)->toBe('cus_01M3FJ0PSYY3VZ0E26HSTEZ7TX')
        ->and($customer->points())->toBe(349)
        ->and($customer->balance->value->amount)->toBe('3.49')
        ->and($customer->tier->name)->toBe('Silver')
        ->and($customer->tier_progress->next_tier->threshold)->toBe(1500)
        ->and($customer->lifetime->earned_points)->toBe(349)
        ->and($customer['card_number'])->toBe('4821773019');
});

it('reads the balance endpoint', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::customer()));
    $customer = $http->client()->customers->balance('cus_1');

    expect($http->route())->toBe('GET /customers/cus_1/balance')
        ->and($customer->points())->toBe(349);
});

it('updates a customer with PATCH', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::customer()));
    $http->client()->customers->update('cus_1', ['email' => 'Jane.Doe@Example.com']);

    expect($http->route())->toBe('PATCH /customers/cus_1')
        ->and($http->body())->toBe(['email' => 'Jane.Doe@Example.com']);
});

it('deletes a customer and expects no body', function () {
    $http = fakeHttp(new Response(204));
    $http->client()->customers->delete('cus_1');

    expect($http->route())->toBe('DELETE /customers/cus_1');
});

it('finds a customer by external id, email, phone or card number', function (string $method, array $args, array $query) {
    $http = fakeHttp(jsonResponse(200, Fixtures::list([Fixtures::customer()])));
    $customer = $http->client()->customers->{$method}(...$args);

    expect($customer)->toBeInstanceOf(Customer::class)
        ->and($http->route())->toBe('GET /customers')
        ->and($http->query())->toBe($query);
})->with([
    'external id' => ['findByExternalId', ['1042', 'woocommerce'], ['external_id' => '1042', 'external_source' => 'woocommerce', 'limit' => '1']],
    'email' => ['findByEmail', ['jane@example.com'], ['email' => 'jane@example.com', 'limit' => '1']],
    'phone' => ['findByPhone', ['0722 123 456'], ['phone' => '0722 123 456', 'limit' => '1']],
    'card number' => ['findByCardNumber', ['4821 773 019'], ['card_number' => '4821 773 019', 'limit' => '1']],
]);

it('returns null when no customer matches', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::list()));

    expect($http->client()->customers->findByEmail('nobody@example.com'))->toBeNull();
});

it('lists a customer\'s ledger, buckets and referrals as typed items', function () {
    $http = fakeHttp(
        jsonResponse(200, Fixtures::list([['id' => '417622', 'object' => 'point_transaction', 'type' => 'credit', 'kind' => 'earn', 'amount' => 49, 'amount_signed' => 49]])),
        jsonResponse(200, Fixtures::list([Fixtures::bucket()])),
        jsonResponse(200, Fixtures::list([['id' => 'ref_1', 'object' => 'referral', 'status' => 'rewarded']])),
    );
    $client = $http->client();

    $transactions = $client->customers->transactions('cus_1', ['limit' => 50]);
    $buckets = $client->customers->buckets('cus_1', ['status' => 'active']);
    $referrals = $client->customers->referrals('cus_1');

    expect($transactions)->toBeInstanceOf(Collection::class)
        ->and($transactions->first())->toBeInstanceOf(PointTransaction::class)
        ->and($transactions->first()->kind)->toBe('earn')
        ->and($buckets->first())->toBeInstanceOf(PointBucket::class)
        ->and($referrals->first())->toBeInstanceOf(Referral::class)
        ->and($http->route(0))->toBe('GET /customers/cus_1/transactions')
        ->and($http->query(0))->toBe(['limit' => '50'])
        ->and($http->route(1))->toBe('GET /customers/cus_1/buckets')
        ->and($http->route(2))->toBe('GET /customers/cus_1/referrals');
});
