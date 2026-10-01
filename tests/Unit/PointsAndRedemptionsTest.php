<?php

declare(strict_types=1);

use Quoyer\ErrorCode;
use Quoyer\Exceptions\AccountStateException;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Exceptions\NotFoundException;
use Quoyer\Resources\PointBucket;
use Quoyer\Resources\PointCreditResult;
use Quoyer\Resources\PointCreditReversalResult;
use Quoyer\Resources\PointsPreview;
use Quoyer\Resources\Redemption;
use Quoyer\Tests\Support\Fixtures;

it('credits points and returns every bucket typed', function () {
    $http = fakeHttp(jsonResponse(201, Fixtures::creditResult()));
    $result = $http->client()->points->credit(['customer_id' => 'cus_1', 'rule_type' => 'purchase', 'source_reference' => 'o_1', 'metadata' => ['amount' => 49.99]]);

    expect($http->route())->toBe('POST /points/credit')
        ->and($result)->toBeInstanceOf(PointCreditResult::class)
        ->and($result->pointsAwarded())->toBe(49)
        ->and($result->wasNew())->toBeTrue()
        ->and($result->bucket)->toBeInstanceOf(PointBucket::class)
        ->and($result->buckets[0]->metadata['amount'])->toBe(49.99);
});

it('reports a replayed credit as not new', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::creditResult(wasNew: false)));

    expect($http->client()->points->credit(['customer_id' => 'cus_1', 'rule_type' => 'purchase', 'source_reference' => 'o_1'])->wasNew())->toBeFalse();
});

it('refuses a credit without a source_reference before sending anything', function () {
    $http = fakeHttp();

    expect(fn () => $http->client()->points->credit(['customer_id' => 'cus_1', 'rule_type' => 'purchase']))
        ->toThrow(InvalidArgumentException::class, 'source_reference')
        ->and($http->sentCount())->toBe(0);
});

it('reverses a credit', function () {
    $http = fakeHttp(jsonResponse(201, Fixtures::creditReversal()));
    $result = $http->client()->points->reverseCredit(['customer_id' => 'cus_1', 'source_reference' => 'o_1', 'reason' => 'refund']);

    expect($http->route())->toBe('POST /points/credit/reverse')
        ->and($result)->toBeInstanceOf(PointCreditReversalResult::class)
        ->and($result->wasNewlyReversed())->toBeTrue()
        ->and($result->pointsReversed())->toBe(49);
});

it('treats an already-reversed credit (409) as the success it is', function () {
    $http = fakeHttp(jsonResponse(409, Fixtures::creditReversal(newlyReversed: false)));
    $result = $http->client()->points->reverseCredit(['customer_id' => 'cus_1', 'source_reference' => 'o_1']);

    expect($result->wasNewlyReversed())->toBeFalse()
        ->and($result->pointsReversed())->toBe(0)
        ->and($result->getLastResponse()->statusCode)->toBe(409);
});

it('throws bucket_not_found when nothing was credited for the reference', function () {
    $http = fakeHttp(jsonResponse(404, Fixtures::error('invalid_request', 'bucket_not_found', 'No credited bucket matched.')));

    try {
        $http->client()->points->reverseCredit(['customer_id' => 'cus_1', 'source_reference' => 'o_404']);
        $this->fail('Expected NotFoundException');
    } catch (NotFoundException $e) {
        expect($e->getErrorCode())->toBe(ErrorCode::BUCKET_NOT_FOUND);
    }
});

it('creates a redemption with a coupon reference', function () {
    $http = fakeHttp(jsonResponse(201, Fixtures::redemption(['was_new' => true])));
    $redemption = $http->client()->redemptions->create([
        'customer_external_id' => '1042', 'customer_external_source' => 'woocommerce',
        'points' => 100, 'generate_coupon_code' => true, 'source_reference' => 'cart_7_1',
    ]);

    expect($http->route())->toBe('POST /redemptions')
        ->and($redemption)->toBeInstanceOf(Redemption::class)
        ->and($redemption->coupon_code)->toBe('QYR-516688D8')
        ->and($redemption->wasNew())->toBeTrue()
        ->and($redemption->isReversed())->toBeFalse()
        ->and($redemption->bucket_consumption[1]->amount)->toBe(51);
});

it('refuses a redemption without points or a reference', function (array $params, string $missing) {
    $http = fakeHttp();

    expect(fn () => $http->client()->redemptions->create($params))->toThrow(InvalidArgumentException::class, $missing)
        ->and($http->sentCount())->toBe(0);
})->with([
    [['source_reference' => 'r'], 'points'],
    [['points' => 100], 'source_reference'],
]);

it('surfaces a suspended account\'s redemption refusal with a shopper-safe message', function () {
    $http = fakeHttp(jsonResponse(403, Fixtures::error(
        'account_state_error',
        'redemption_unavailable',
        'Point redemption is temporarily unavailable for this store.',
        ['admin_reason' => 'Account suspended for non-payment.'],
    )));

    try {
        $http->client()->redemptions->create(['customer_id' => 'cus_1', 'points' => 100, 'source_reference' => 'r']);
        $this->fail('Expected AccountStateException');
    } catch (AccountStateException $e) {
        expect($e->getErrorCode())->toBe(ErrorCode::REDEMPTION_UNAVAILABLE)
            ->and($e->isShopperSafe())->toBeTrue()
            ->and($e->getMessage())->toBe('Point redemption is temporarily unavailable for this store.')
            ->and($e->getAdminReason())->toBe('Account suspended for non-payment.');
    }
});

it('reverses a redemption with a reason', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::redemption(['status' => 'reversed', 'was_newly_reversed' => true, 'points_restored' => 100])));
    $redemption = $http->client()->redemptions->reverse('red_1', ['reason' => 'coupon removed', 'triggered_by' => ['cart_id' => 7]]);

    expect($http->route())->toBe('POST /redemptions/red_1/reverse')
        ->and($http->body())->toBe(['reason' => 'coupon removed', 'triggered_by' => ['cart_id' => 7]])
        ->and($redemption->isReversed())->toBeTrue()
        ->and($redemption->wasNewlyReversed())->toBeTrue()
        ->and($redemption->points_restored)->toBe(100);
});

it('merges metadata into a redemption', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::redemption()));
    $http->client()->redemptions->mergeMetadata('red_1', ['order_id' => 5532, 'order_reference' => 'WC-5532']);

    expect($http->route())->toBe('POST /redemptions/red_1/metadata')
        ->and($http->body())->toBe(['metadata' => ['order_id' => 5532, 'order_reference' => 'WC-5532']]);
});

it('finds a redemption by source reference across every type', function () {
    $http = fakeHttp(jsonResponse(200, Fixtures::list([Fixtures::redemption()])));
    $redemption = $http->client()->redemptions->findBySourceReference('woocommerce_cart_7_1');

    expect($redemption)->toBeInstanceOf(Redemption::class)
        ->and($http->query())->toBe(['source_reference' => 'woocommerce_cart_7_1', 'type' => 'all', 'limit' => '1']);
});

it('previews what an order would earn', function () {
    $http = fakeHttp(jsonResponse(200, [
        'object' => 'points_preview', 'points' => 74, 'base_points' => 49, 'tier_bonus_points' => 25, 'currency' => 'EUR',
        'tier' => ['id' => 'tir_1', 'name' => 'Gold', 'multiplier' => 150, 'purchase_discount_percent' => 10], 'program_active' => true,
    ]));
    $preview = $http->client()->points->preview(['amount' => 49.99, 'customer_id' => 'cus_1']);

    expect($http->route())->toBe('POST /points/preview')
        ->and($preview)->toBeInstanceOf(PointsPreview::class)
        ->and($preview->points())->toBe(74)
        ->and($preview->tier->purchase_discount_percent)->toBe(10);
    expect(fn () => $http->client()->points->preview([]))->toThrow(InvalidArgumentException::class, 'amount');
});
