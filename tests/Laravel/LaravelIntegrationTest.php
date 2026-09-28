<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Laravel\Facades\Quoyer;
use Quoyer\QuoyerClient;
use Quoyer\Services\CustomerService;
use Quoyer\Webhook;

it('binds one client built from config', function () {
    config()->set('quoyer.store_url', 'https://shop.example.com');
    config()->set('quoyer.max_retries', 5);

    $client = app(QuoyerClient::class);

    expect($client)->toBe(app(QuoyerClient::class))
        ->and($client)->toBe(app('quoyer'))
        ->and($client->options()->apiKey)->toBe('quoy_sandbox_laravel.secret')
        ->and($client->options()->apiBase())->toBe('https://quoyer.example/api/v1')
        ->and($client->options()->storeUrl)->toBe('https://shop.example.com')
        ->and($client->options()->maxRetries)->toBe(5);
});

it('ignores empty env values instead of sending them', function () {
    config()->set('quoyer.store_url', '');
    config()->set('quoyer.platform', null);

    expect(app(QuoyerClient::class)->options()->storeUrl)->toBeNull();
});

it('fails on first use, not at boot, without an API key', function () {
    config()->set('quoyer.api_key', null);

    app(QuoyerClient::class);
})->throws(InvalidArgumentException::class, 'api_key');

it('reaches the services through the facade', function () {
    expect(Quoyer::customers())->toBeInstanceOf(CustomerService::class);
});

it('publishes the config file', function () {
    $this->artisan('vendor:publish', ['--tag' => 'quoyer-config', '--force' => true])->assertSuccessful();

    expect(config_path('quoyer.php'))->toBeFile();
});

describe('the quoyer.webhook middleware', function () {
    beforeEach(function () {
        Route::post('/webhooks/quoyer', fn (Request $request) => response()->json([
            'type' => $request->attributes->get('quoyer_event')->type,
        ]))->middleware('quoyer.webhook');
    });

    it('passes a correctly signed delivery with its event', function () {
        $body = '{"id":"evt_1","object":"event","type":"points.expired","data":{}}';

        $this->call('POST', '/webhooks/quoyer', [], [], [], [
            'HTTP_X_QUOYER_SIGNATURE' => Webhook::generateSignatureHeader($body, 'whsec_laravel'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk()->assertJson(['type' => 'points.expired']);
    });

    it('answers 400 to a bad signature and never reaches the route', function () {
        $body = '{"id":"evt_1","object":"event","type":"points.expired","data":{}}';

        $this->call('POST', '/webhooks/quoyer', [], [], [], [
            'HTTP_X_QUOYER_SIGNATURE' => Webhook::generateSignatureHeader($body, 'whsec_someone_else'),
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertStatus(400);
    });

    it('answers 400 without a signature', function () {
        $this->postJson('/webhooks/quoyer', ['id' => 'evt_1'])->assertStatus(400);
    });
});
