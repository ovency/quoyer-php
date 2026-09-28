<?php

declare(strict_types=1);

use Quoyer\Collection;
use Quoyer\QuoyerClient;
use Quoyer\Resources\Me;
use Quoyer\Resources\Program;
use Quoyer\Resources\TierList;

/*
 * Runs against a real Quoyer install when these are set, and is skipped otherwise:
 *
 *   QUOYER_TEST_API_KEY    a key of a TEST merchant (never a live shop's)
 *   QUOYER_TEST_BASE_URL   e.g. https://quoyer.test, or a staging install
 *   QUOYER_TEST_WRITES=1   also run the tests that create data
 */

function liveClient(): QuoyerClient
{
    $key = getenv('QUOYER_TEST_API_KEY');
    $base = getenv('QUOYER_TEST_BASE_URL');

    if (! $key || ! $base) {
        test()->markTestSkipped('Set QUOYER_TEST_API_KEY and QUOYER_TEST_BASE_URL to run against a real install.');
    }

    return new QuoyerClient(['api_key' => $key, 'base_url' => $base, 'max_retries' => 0]);
}

it('reads the account', function () {
    $me = liveClient()->me();

    expect($me)->toBeInstanceOf(Me::class)
        ->and($me->tenant->id)->toStartWith('tnt_')
        ->and($me->accountState())->toBeIn(['trial', 'active', 'past_due', 'suspended', 'cancelled']);
});

it('reads the programme, tiers, rules and currencies', function () {
    $client = liveClient();

    expect($client->program->retrieve())->toBeInstanceOf(Program::class)
        ->and($client->tiers->list())->toBeInstanceOf(TierList::class)
        ->and($client->earningRules->list(['include_inactive' => true]))->toBeInstanceOf(Collection::class)
        ->and($client->currencies->list(['active' => true]))->toBeInstanceOf(Collection::class);
});

it('pages through customers', function () {
    $page = liveClient()->customers->list(['limit' => 2]);

    expect($page)->toBeInstanceOf(Collection::class)
        ->and(count($page))->toBeLessThanOrEqual(2);
});

it('upserts and finds a test customer', function () {
    if (getenv('QUOYER_TEST_WRITES') !== '1') {
        $this->markTestSkipped('Set QUOYER_TEST_WRITES=1 to run tests that create data.');
    }

    $client = liveClient();
    $externalId = 'sdk-test-'.bin2hex(random_bytes(4));

    $created = $client->customers->upsert([
        'external_id' => $externalId,
        'external_source' => 'quoyer-php-tests',
        'email' => $externalId.'@example.com',
        'first_name' => 'SDK',
        'last_name' => 'Test',
    ]);

    $found = $client->customers->findByExternalId($externalId, 'quoyer-php-tests');

    expect($created->wasCreated())->toBeTrue()
        ->and($found?->id)->toBe($created->id);

    $client->customers->delete($created->id);
});
