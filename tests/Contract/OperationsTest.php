<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response;
use Quoyer\QuoyerClient;
use Quoyer\Tests\Support\Fixtures;
use Symfony\Component\Yaml\Yaml;

/**
 * Every operation in Quoyer's API contract (by operationId), the route it
 * is, and the SDK call that performs it.
 *
 * When the contract gains an operation, "covers every operation in the
 * contract" fails until the SDK covers it: add the method, add it here,
 * release a minor version.
 *
 * @return array<string, array{0: string, 1: Closure(QuoyerClient): mixed}>
 */
function operations(): array
{
    return [
        'getMe' => ['GET /me', fn (QuoyerClient $q) => $q->me()],
        'listCustomers' => ['GET /customers', fn (QuoyerClient $q) => $q->customers->list()],
        'upsertCustomer' => ['POST /customers', fn (QuoyerClient $q) => $q->customers->upsert(['email' => 'a@example.com'])],
        'getCustomer' => ['GET /customers/cus_1', fn (QuoyerClient $q) => $q->customers->retrieve('cus_1')],
        'updateCustomer' => ['PATCH /customers/cus_1', fn (QuoyerClient $q) => $q->customers->update('cus_1', ['first_name' => 'A'])],
        'deleteCustomer' => ['DELETE /customers/cus_1', fn (QuoyerClient $q) => $q->customers->delete('cus_1')],
        'getCustomerBalance' => ['GET /customers/cus_1/balance', fn (QuoyerClient $q) => $q->customers->balance('cus_1')],
        'listCustomerTransactions' => ['GET /customers/cus_1/transactions', fn (QuoyerClient $q) => $q->customers->transactions('cus_1')],
        'listCustomerReferrals' => ['GET /customers/cus_1/referrals', fn (QuoyerClient $q) => $q->customers->referrals('cus_1')],
        'listCustomerBuckets' => ['GET /customers/cus_1/buckets', fn (QuoyerClient $q) => $q->customers->buckets('cus_1')],
        'listBuckets' => ['GET /buckets', fn (QuoyerClient $q) => $q->buckets->list()],
        'getBucket' => ['GET /buckets/bkt_1', fn (QuoyerClient $q) => $q->buckets->retrieve('bkt_1')],
        'awardCustomerEvent' => ['POST /customers/cus_1/events', fn (QuoyerClient $q) => $q->points->awardEvent('cus_1', ['event' => 'yoga_class', 'source_reference' => 'b'])],
        'creditPoints' => ['POST /points/credit', fn (QuoyerClient $q) => $q->points->credit(['customer_id' => 'cus_1', 'rule_type' => 'signup', 'source_reference' => 's'])],
        'reverseCredit' => ['POST /points/credit/reverse', fn (QuoyerClient $q) => $q->points->reverseCredit(['customer_id' => 'cus_1', 'source_reference' => 's'])],
        'previewPoints' => ['POST /points/preview', fn (QuoyerClient $q) => $q->points->preview(['amount' => 49.99])],
        'listRedemptions' => ['GET /redemptions', fn (QuoyerClient $q) => $q->redemptions->list()],
        'createRedemption' => ['POST /redemptions', fn (QuoyerClient $q) => $q->redemptions->create(['customer_id' => 'cus_1', 'points' => 100, 'source_reference' => 'r'])],
        'getRedemption' => ['GET /redemptions/red_1', fn (QuoyerClient $q) => $q->redemptions->retrieve('red_1')],
        'reverseRedemption' => ['POST /redemptions/red_1/reverse', fn (QuoyerClient $q) => $q->redemptions->reverse('red_1')],
        'listRewards' => ['GET /rewards', fn (QuoyerClient $q) => $q->rewards->list()],
        'getReward' => ['GET /rewards/rwd_1', fn (QuoyerClient $q) => $q->rewards->retrieve('rwd_1')],
        'redeemReward' => ['POST /rewards/rwd_1/redeem', fn (QuoyerClient $q) => $q->rewards->redeem('rwd_1', ['customer_id' => 'cus_1', 'source_reference' => 'r'])],
        'mergeRedemptionMetadata' => ['POST /redemptions/red_1/metadata', fn (QuoyerClient $q) => $q->redemptions->mergeMetadata('red_1', ['order_id' => 1])],
        'listEarningRules' => ['GET /earning-rules', fn (QuoyerClient $q) => $q->earningRules->list()],
        'createEarningRule' => ['POST /earning-rules', fn (QuoyerClient $q) => $q->earningRules->create(['name' => 'n', 'type' => 'signup'])],
        'getEarningRule' => ['GET /earning-rules/rul_1', fn (QuoyerClient $q) => $q->earningRules->retrieve('rul_1')],
        'updateEarningRule' => ['PATCH /earning-rules/rul_1', fn (QuoyerClient $q) => $q->earningRules->update('rul_1', ['is_active' => false])],
        'listCatalogue' => ['GET /catalogue', fn (QuoyerClient $q) => $q->catalogue->list()],
        'upsertCatalogue' => ['POST /catalogue', fn (QuoyerClient $q) => $q->catalogue->upsert([['kind' => 'brand', 'id' => 1, 'name' => 'B']])],
        'listCurrencies' => ['GET /currencies', fn (QuoyerClient $q) => $q->currencies->list()],
        'createCurrency' => ['POST /currencies', fn (QuoyerClient $q) => $q->currencies->create(['currency_code' => 'CHF', 'currency_units_per_point' => 100])],
        'getCurrency' => ['GET /currencies/EUR', fn (QuoyerClient $q) => $q->currencies->retrieve('EUR')],
        'updateCurrency' => ['PATCH /currencies/EUR', fn (QuoyerClient $q) => $q->currencies->update('EUR', ['is_active' => true])],
        'listTiers' => ['GET /tiers', fn (QuoyerClient $q) => $q->tiers->list()],
        'getProgram' => ['GET /program', fn (QuoyerClient $q) => $q->program->retrieve()],
        'updateProgram' => ['PATCH /program', fn (QuoyerClient $q) => $q->program->update(['name' => 'P'])],
    ];
}

it('performs the operation', function (string $route, Closure $call) {
    $http = fakeHttp(str_starts_with($route, 'DELETE') ? new Response(204) : jsonResponse(200, Fixtures::list()));

    $call($http->client());

    expect($http->route())->toBe($route);
})->with(operations());

it('covers every operation in the contract', function () {
    $path = getenv('QUOYER_OPENAPI_PATH') ?: dirname(__DIR__, 3).'/quoyer/docs/openapi.yaml';

    if (! is_file($path)) {
        $this->markTestSkipped('No contract to compare with. Set QUOYER_OPENAPI_PATH to the Quoyer app\'s docs/openapi.yaml.');
    }

    $inContract = [];
    foreach (Yaml::parseFile($path)['paths'] as $operations) {
        foreach ($operations as $operation) {
            if (is_array($operation) && isset($operation['operationId'])) {
                $inContract[] = $operation['operationId'];
            }
        }
    }

    $covered = array_keys(operations());

    expect(array_values(array_diff($inContract, $covered)))->toBe([], 'Operations in the contract the SDK does not cover')
        ->and(array_values(array_diff($covered, $inContract)))->toBe([], 'SDK operations the contract no longer has');
});
