<?php

declare(strict_types=1);

use Quoyer\Collection;
use Quoyer\QuoyerObject;
use Quoyer\Resources\Customer;
use Quoyer\Tests\Support\Fixtures;

it('keeps fields the SDK does not know yet', function () {
    $customer = Customer::constructFrom(Fixtures::customer(['loyalty_score' => 97, 'wallet' => ['pass_url' => 'https://…']]));

    expect($customer->loyalty_score)->toBe(97)
        ->and($customer->wallet)->toBeInstanceOf(QuoyerObject::class)
        ->and($customer->wallet->pass_url)->toBe('https://…')
        ->and($customer->toArray()['loyalty_score'])->toBe(97);
});

it('returns null for an absent field and tells absent from null', function () {
    $customer = Customer::constructFrom(Fixtures::customer());

    expect($customer->does_not_exist)->toBeNull()
        ->and($customer->has('phone'))->toBeTrue()
        ->and($customer->phone)->toBeNull()
        ->and($customer->has('does_not_exist'))->toBeFalse()
        ->and(isset($customer->email))->toBeTrue()
        ->and(isset($customer->phone))->toBeFalse();
});

it('is read-only', function (Closure $write) {
    $write(Customer::constructFrom(Fixtures::customer()));
})->with([
    'property' => [fn ($c) => $c->email = 'x'],
    'array key' => [fn ($c) => $c['email'] = 'x'],
    'unset' => [function ($c) {
        unset($c->email);
    }],
])->throws(LogicException::class);

it('serialises back to exactly what the API sent', function () {
    $raw = Fixtures::customer();

    expect(json_decode(json_encode(Customer::constructFrom($raw)), true))->toBe($raw);
});

it('walks every page, sending each next_cursor back', function () {
    $http = fakeHttp(
        jsonResponse(200, Fixtures::list([Fixtures::customer(['id' => 'cus_1']), Fixtures::customer(['id' => 'cus_2'])], 'cursor_a')),
        jsonResponse(200, Fixtures::list([Fixtures::customer(['id' => 'cus_3'])], 'cursor_b')),
        jsonResponse(200, Fixtures::list([Fixtures::customer(['id' => 'cus_4'])])),
    );

    $ids = [];
    foreach ($http->client()->customers->all(['external_source' => 'myshop']) as $customer) {
        $ids[] = $customer->id;
    }

    expect($ids)->toBe(['cus_1', 'cus_2', 'cus_3', 'cus_4'])
        ->and($http->query(0))->toBe(['external_source' => 'myshop', 'limit' => '100'])
        ->and($http->query(1))->toBe(['external_source' => 'myshop', 'limit' => '100', 'cursor' => 'cursor_a'])
        ->and($http->query(2)['cursor'])->toBe('cursor_b');
});

it('fetches pages lazily', function () {
    $http = fakeHttp(
        jsonResponse(200, Fixtures::list([Fixtures::customer(['id' => 'cus_1'])], 'cursor_a')),
        jsonResponse(200, Fixtures::list([Fixtures::customer(['id' => 'cus_2'])])),
    );

    $first = $http->client()->customers->all()->current();

    expect($first->id)->toBe('cus_1')
        ->and($http->sentCount())->toBe(1);
});

it('steps through pages by hand', function () {
    $http = fakeHttp(
        jsonResponse(200, Fixtures::list([Fixtures::customer()], 'cursor_a')),
        jsonResponse(200, Fixtures::list([Fixtures::customer()])),
    );

    $page = $http->client()->customers->list(['limit' => 1]);
    $next = $page->nextPage();

    expect($page)->toBeInstanceOf(Collection::class)
        ->and($page->hasMore())->toBeTrue()
        ->and($page->nextCursor())->toBe('cursor_a')
        ->and($next)->toBeInstanceOf(Collection::class)
        ->and($next->hasMore())->toBeFalse()
        ->and($next->nextPage())->toBeNull()
        ->and($http->query(1))->toBe(['limit' => '1', 'cursor' => 'cursor_a']);
});

it('iterates and counts one page', function () {
    $page = Collection::constructFrom(Fixtures::list([Fixtures::customer(), Fixtures::customer()]));

    expect($page)->toHaveCount(2)
        ->and(iterator_to_array($page))->each->toBeInstanceOf(Customer::class)
        ->and($page->isEmpty())->toBeFalse();
});
