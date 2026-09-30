<?php

declare(strict_types=1);

use Quoyer\Enums\CatalogueKind;
use Quoyer\Enums\RuleType;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Resources\CatalogueSyncResult;
use Quoyer\Resources\Currency;
use Quoyer\Resources\EarningRule;
use Quoyer\Resources\Me;
use Quoyer\Resources\PointBucket;
use Quoyer\Resources\Program;
use Quoyer\Resources\Redemption;
use Quoyer\Resources\Reward;
use Quoyer\Resources\Tier;
use Quoyer\Resources\TierList;
use Quoyer\Tests\Support\Fixtures;

it('reads the account and its entitlements', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::me(), ['X-Quoyer-Deprecation' => 'limits, rate_limits, tenant.tier']));
    $me = $http->client()->me();

    expect($me)->toBeInstanceOf(Me::class)
        ->and($me->tenant->name)->toBe('Example Cosmetics')
        ->and($me->accountState())->toBe('active')
        ->and($me->hasFeature('data_export'))->toBeTrue()
        ->and($me->hasFeature('vip_tiers'))->toBeFalse()
        ->and($me->hasFeature('something_new'))->toBeFalse()
        ->and($me->limit('members_active'))->toBe(2000)
        ->and($me->limit('api_per_month'))->toBeNull()   // null = unlimited
        ->and($me->limit('never_heard_of_it'))->toBe(0)  // missing = zero
        ->and($me->brand->powered_by_url)->toStartWith('https://quoyer.com/?ref=')
        ->and($me->getLastResponse()->deprecation())->toBe('limits, rate_limits, tenant.tier');
});

it('lists and retrieves buckets', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::list([Fixtures::bucket()])), jsonResponse(200, Fixtures::bucket()));
    $client = $http->client();

    $list = $client->buckets->list(['customer_external_id' => '1042', 'customer_external_source' => 'woocommerce']);
    $bucket = $client->buckets->retrieve('bkt_01M3FJ0PXV6B3F7T4889FFW82X');

    expect($list->first())->toBeInstanceOf(PointBucket::class)
        ->and($http->route(0))->toBe('GET /buckets')
        ->and($http->route(1))->toBe('GET /buckets/bkt_01M3FJ0PXV6B3F7T4889FFW82X')
        ->and($bucket->earning_rule->type)->toBe('purchase');
});

it('manages earning rules', function () {
    $rule = ['id' => 'rul_1', 'object' => 'earning_rule', 'name' => 'Double points', 'type' => 'purchase', 'target_category_ids' => ['5', '12']];
    $http = fakeHttp(jsonResponse(201, $rule), jsonResponse(200, $rule), jsonResponse(200, $rule), jsonResponse(200, Fixtures::list([$rule])));
    $client = $http->client();

    $created = $client->earningRules->create(['name' => 'Double points', 'type' => RuleType::Purchase, 'stacks' => true, 'points_multiplier' => 200, 'target_category_ids' => ['5', 12]]);
    $client->earningRules->update('rul_1', ['is_active' => false]);
    $client->earningRules->retrieve('rul_1');
    $client->earningRules->list(['include_inactive' => true]);

    expect($created)->toBeInstanceOf(EarningRule::class)
        ->and($created->target_category_ids)->toBe(['5', '12'])
        ->and($http->body(0)['type'])->toBe('purchase')
        ->and($http->route(0))->toBe('POST /earning-rules')
        ->and($http->route(1))->toBe('PATCH /earning-rules/rul_1')
        ->and($http->route(2))->toBe('GET /earning-rules/rul_1')
        ->and($http->route(3))->toBe('GET /earning-rules');
});

it('refuses an earning rule without a name or type', function () {
    fakeHttp()->client()->earningRules->create(['name' => 'Nameless type']);
})->throws(InvalidArgumentException::class, 'type');

it('upserts a catalogue batch', function () {
    $http = fakeHttp(jsonResponse(201, ['object' => 'catalogue_sync_result', 'received' => 2, 'upserted' => 2, 'skipped' => 0]));
    $result = $http->client()->catalogue->upsert([
        ['kind' => CatalogueKind::Category, 'id' => '5', 'name' => 'Skincare'],
        ['kind' => 'product', 'id' => 77, 'name' => 'Night cream'],
    ]);

    expect($result)->toBeInstanceOf(CatalogueSyncResult::class)
        ->and($result->upserted)->toBe(2)
        ->and($http->body()['items'][0]['kind'])->toBe('category');
});

it('refuses an empty or oversized catalogue batch', function (int $size) {
    fakeHttp()->client()->catalogue->upsert(array_fill(0, $size, ['kind' => 'product', 'id' => 1, 'name' => 'x']));
})->with([0, 1001])->throws(InvalidArgumentException::class);

it('syncs a large catalogue in batches and adds up the counts', function () {
    $http = fakeHttp(
        jsonResponse(201, ['object' => 'catalogue_sync_result', 'received' => 2, 'upserted' => 2, 'skipped' => 0]),
        jsonResponse(201, ['object' => 'catalogue_sync_result', 'received' => 2, 'upserted' => 1, 'skipped' => 1]),
        jsonResponse(201, ['object' => 'catalogue_sync_result', 'received' => 1, 'upserted' => 1, 'skipped' => 0]),
    );

    $items = (function () {
        foreach (range(1, 5) as $id) {
            yield ['kind' => 'product', 'id' => $id, 'name' => "Product {$id}"];
        }
    })();

    $result = $http->client()->catalogue->sync($items, batchSize: 2);

    expect($http->sentCount())->toBe(3)
        ->and(count($http->body(2)['items']))->toBe(1)
        ->and($result->received)->toBe(5)
        ->and($result->upserted)->toBe(4)
        ->and($result->skipped)->toBe(1);
});

it('manages currencies', function () {
    $currency = ['id' => 'CHF', 'object' => 'currency', 'currency_code' => 'CHF', 'currency_units_per_point' => 100, 'point_value_minor_units' => 1];
    $http = fakeHttp(jsonResponse(201, $currency), jsonResponse(200, $currency), jsonResponse(200, Fixtures::list([$currency])));
    $client = $http->client();

    $created = $client->currencies->create(['currency_code' => 'CHF', 'currency_units_per_point' => 100]);
    $client->currencies->update('chf', ['currency_units_per_point' => 50]);
    $client->currencies->list(['active' => true]);

    expect($created)->toBeInstanceOf(Currency::class)
        ->and($http->route(1))->toBe('PATCH /currencies/chf')
        ->and($http->query(2))->toBe(['active' => 'true']);
});

it('reads and updates the programme', function () {
    $program = ['object' => 'program', 'name' => 'Example Rewards', 'is_active' => true, 'minimum_redemption_points' => 50, 'currencies' => [['currency_code' => 'EUR', 'is_default' => true]]];
    $http = fakeHttp(jsonResponse(200, $program), jsonResponse(200, $program));
    $client = $http->client();

    $read = $client->program->retrieve();
    $client->program->update(['name' => 'Example Rewards']);

    expect($read)->toBeInstanceOf(Program::class)
        ->and($read->minimum_redemption_points)->toBe(50)
        ->and($read->currencies[0]->currency_code)->toBe('EUR')
        ->and($http->route(1))->toBe('PATCH /program');
});

it('reads the VIP ladder and whether it applies', function () {
    $http = fakeHttp(jsonResponse(200, [
        'object' => 'list', 'active' => true, 'has_more' => false, 'next_cursor' => null,
        'data' => [
            ['id' => 'tir_1', 'object' => 'tier', 'name' => 'Bronze', 'threshold' => 0, 'multiplier' => 100],
            ['id' => 'tir_2', 'object' => 'tier', 'name' => 'Silver', 'threshold' => 500, 'multiplier' => 125],
        ],
    ]));
    $tiers = $http->client()->tiers->list();

    expect($tiers)->toBeInstanceOf(TierList::class)
        ->and($tiers->isActive())->toBeTrue()
        ->and($tiers)->toHaveCount(2)
        ->and($tiers->first())->toBeInstanceOf(Tier::class)
        ->and($tiers->data()[1]->threshold)->toBe(500);
});

it('exposes services as methods too, for the facade', function () {
    $client = fakeHttp()->client();

    expect($client->customers())->toBe($client->customers)
        ->and($client->points())->toBe($client->points)
        ->and($client->redemptions())->toBe($client->redemptions)
        ->and($client->buckets())->toBe($client->buckets)
        ->and($client->earningRules())->toBe($client->earningRules)
        ->and($client->catalogue())->toBe($client->catalogue)
        ->and($client->currencies())->toBe($client->currencies)
        ->and($client->program())->toBe($client->program)
        ->and($client->tiers())->toBe($client->tiers);
});

it('lists rewards for a customer and redeems one as a redemption carrying the reward', function () {
    $reward = [
        'id' => 'rwd_1', 'object' => 'reward', 'name' => 'A free coffee', 'description' => null, 'type' => 'free_product',
        'points_cost' => 120, 'amount' => null, 'amount_minor_units' => null, 'currency' => null, 'percent' => null, 'product_id' => '77',
        'per_customer_limit' => 1, 'starts_at' => null, 'ends_at' => null, 'is_available' => true,
        'customer' => ['can_redeem' => true, 'reason' => null, 'points_short' => 0, 'held' => 0],
        'created_at' => '2026-09-30T10:00:00+00:00', 'updated_at' => '2026-09-30T10:00:00+00:00',
    ];
    $snapshot = ['id' => 'rwd_1', 'name' => 'A free coffee', 'type' => 'free_product', 'points_cost' => 120, 'amount' => null, 'currency' => null, 'percent' => null, 'product_id' => '77'];
    $http = fakeHttp(
        jsonResponse(200, Fixtures::list([$reward])),
        jsonResponse(201, Fixtures::redemption(['reward' => $snapshot, 'points_redeemed' => 120, 'was_new' => true])),
    );
    $client = $http->client();

    $list = $client->rewards->list(['customer_external_id' => '1042', 'customer_external_source' => 'woocommerce']);
    $redemption = $client->rewards->redeem('rwd_1', ['customer_external_id' => '1042', 'customer_external_source' => 'woocommerce', 'source_reference' => 'cart_7_reward_1']);

    expect($list->first())->toBeInstanceOf(Reward::class)
        ->and($list->first()->canRedeemFor())->toBeTrue()
        ->and($list->first()->type)->toBe(Reward::FREE_PRODUCT)
        ->and($http->route(0))->toBe('GET /rewards')
        ->and($http->route(1))->toBe('POST /rewards/rwd_1/redeem')
        ->and($redemption)->toBeInstanceOf(Redemption::class)
        ->and($redemption->wasNew())->toBeTrue()
        ->and($redemption->reward->product_id)->toBe('77');
});

it('refuses a reward redemption without a source_reference before sending anything', function () {
    $http = fakeHttp();

    expect(fn () => $http->client()->rewards->redeem('rwd_1', ['customer_id' => 'cus_1']))
        ->toThrow(InvalidArgumentException::class, 'source_reference')
        ->and($http->sentCount())->toBe(0);
});
