<?php

declare(strict_types=1);

use Quoyer\Enums\EventType;
use Quoyer\Exceptions\SignatureVerificationException;
use Quoyer\Resources\Customer;
use Quoyer\Resources\Event;
use Quoyer\Resources\Redemption;
use Quoyer\Tests\Support\Fixtures;
use Quoyer\Webhook;

// The test vector published in Quoyer's API contract (Webhooks → Signature).
const VECTOR_SECRET = 'whsec_test_4f8a2c';
const VECTOR_TIMESTAMP = 1790000000;
const VECTOR_BODY = '{"id":"evt_01TEST","object":"event","type":"customer.created"}';
const VECTOR_HEADER = 't=1790000000,v1=4c3614a8dee2fe5493c2b446b650906a868553dc8d2d69910fc3c61e0aaa31cd';

it('signs exactly as the contract\'s test vector', function () {
    expect(Webhook::generateSignatureHeader(VECTOR_BODY, VECTOR_SECRET, VECTOR_TIMESTAMP))->toBe(VECTOR_HEADER);
});

it('verifies the test vector and returns its event', function () {
    $event = Webhook::constructEvent(VECTOR_BODY, VECTOR_HEADER, VECTOR_SECRET, now: VECTOR_TIMESTAMP + 10);

    expect($event)->toBeInstanceOf(Event::class)
        ->and($event->id)->toBe('evt_01TEST')
        ->and($event->typeEnum())->toBe(EventType::CustomerCreated)
        ->and($event->isType('customer.created'))->toBeTrue();
});

it('rejects a delivery outside the tolerance', function () {
    Webhook::constructEvent(VECTOR_BODY, VECTOR_HEADER, VECTOR_SECRET, now: VECTOR_TIMESTAMP + 301);
})->throws(SignatureVerificationException::class, 'tolerance');

it('can skip the timestamp check, for tests', function () {
    expect(Webhook::isValidSignature(VECTOR_BODY, VECTOR_HEADER, VECTOR_SECRET, tolerance: null))->toBeTrue();
});

it('rejects a tampered body, a wrong secret and a malformed header', function (string $body, string $header, string $secret) {
    expect(Webhook::isValidSignature($body, $header, $secret, now: VECTOR_TIMESTAMP))->toBeFalse();
})->with([
    'tampered body' => [str_replace('customer.created', 'customer.deleted', VECTOR_BODY), VECTOR_HEADER, VECTOR_SECRET],
    'wrong secret' => [VECTOR_BODY, VECTOR_HEADER, 'whsec_other'],
    'empty secret' => [VECTOR_BODY, VECTOR_HEADER, ''],
    'no header' => [VECTOR_BODY, '', VECTOR_SECRET],
    'no v1' => [VECTOR_BODY, 't=1790000000', VECTOR_SECRET],
    'text timestamp' => [VECTOR_BODY, 't=abc,v1=4c36', VECTOR_SECRET],
]);

it('reads a real event with typed payload objects', function () {
    $body = json_encode([
        'id' => 'evt_01M3FJ7T5F4A7B9C1D3E5F7G9H',
        'object' => 'event',
        'type' => 'redemption.created',
        'created_at' => '2026-09-26T22:10:35+00:00',
        'data' => ['redemption' => Fixtures::redemption()],
    ]);
    $header = Webhook::generateSignatureHeader($body, 'whsec_live');

    $event = Webhook::constructEvent($body, $header, 'whsec_live');

    expect($event->typeEnum())->toBe(EventType::RedemptionCreated)
        ->and($event->data->redemption)->toBeInstanceOf(Redemption::class)
        ->and($event->data->redemption->points_redeemed)->toBe(100);
});

it('types the customer in a customer event', function () {
    $body = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'customer.updated', 'data' => ['customer' => ['id' => 'cus_1', 'object' => 'customer']]]);

    $event = Webhook::constructEvent($body, Webhook::generateSignatureHeader($body, 's'), 's');

    expect($event->data->customer)->toBeInstanceOf(Customer::class);
});

it('keeps an event type it does not know', function () {
    $body = '{"id":"evt_2","object":"event","type":"wallet.pass_installed","data":{}}';

    $event = Webhook::constructEvent($body, Webhook::generateSignatureHeader($body, 's'), 's');

    expect($event->typeEnum())->toBeNull()
        ->and($event->type)->toBe('wallet.pass_installed');
});

it('rejects a signed body that is not a JSON object', function () {
    Webhook::constructEvent('[1,2]', Webhook::generateSignatureHeader('[1,2]', 's'), 's');
})->throws(SignatureVerificationException::class, 'JSON object');
