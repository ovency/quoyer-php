<?php

declare(strict_types=1);

use Quoyer\ClientOptions;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\QuoyerClient;

it('builds a client from a bare API key with production defaults', function () {
    $client = new QuoyerClient('quoy_live_abc.def');

    expect($client->options()->apiKey)->toBe('quoy_live_abc.def')
        ->and($client->options()->apiBase())->toBe('https://quoyer.com/api/v1')
        ->and($client->options()->storeUrl)->toBeNull()
        ->and($client->options()->maxRetries)->toBe(2);
});

it('adds /api/v1 to the base URL once', function (string $baseUrl) {
    expect(ClientOptions::fromArray(['api_key' => 'k', 'base_url' => $baseUrl])->apiBase())
        ->toBe('https://quoyer.example/api/v1');
})->with([
    'origin' => 'https://quoyer.example',
    'trailing slash' => 'https://quoyer.example/',
    'already versioned' => 'https://quoyer.example/api/v1',
    'versioned with slash' => 'https://quoyer.example/api/v1/',
]);

it('refuses a missing, empty or whitespace API key', function (array $options) {
    ClientOptions::fromArray($options);
})->with([
    'missing' => [[]],
    'empty' => [['api_key' => '   ']],
    'inner whitespace' => [['api_key' => "quoy_live_abc\n.def"]],
])->throws(InvalidArgumentException::class);

it('refuses unknown options, naming them', function () {
    ClientOptions::fromArray(['api_key' => 'k', 'apikey' => 'typo']);
})->throws(InvalidArgumentException::class, 'apikey');

it('refuses plain http except on a local development host', function () {
    ClientOptions::fromArray(['api_key' => 'k', 'base_url' => 'http://quoyer.com']);
})->throws(InvalidArgumentException::class, 'https');

it('allows plain http on local development hosts', function (string $baseUrl) {
    expect(ClientOptions::fromArray(['api_key' => 'k', 'base_url' => $baseUrl])->baseUrl)->toBe($baseUrl);
})->with(['http://quoyer.test', 'http://localhost:8000', 'http://127.0.0.1', 'http://app.localhost']);

it('refuses a base URL that is not a URL', function () {
    ClientOptions::fromArray(['api_key' => 'k', 'base_url' => 'quoyer.com']);
})->throws(InvalidArgumentException::class);

it('names no platform in the User-Agent by default', function () {
    $agent = ClientOptions::fromArray(['api_key' => 'k'])->userAgent();

    expect($agent)->toStartWith('quoyer-php/'.QuoyerClient::VERSION.' PHP/')
        ->and($agent)->not->toMatch('#^Quoyer-#');
});

it('names the platform the way Quoyer reads a connected store\'s', function () {
    $agent = ClientOptions::fromArray(['api_key' => 'k', 'platform' => 'myshop/2.1.0'])->userAgent();

    // A connected store's platform is read from a leading Quoyer-{name}/
    expect(preg_match('#^Quoyer-([A-Za-z0-9]+)/#', $agent, $m))->toBe(1)
        ->and($m[1])->toBe('myshop');
});

it('refuses a platform that is not name/version', function (string $platform) {
    ClientOptions::fromArray(['api_key' => 'k', 'platform' => $platform]);
})->with(['myshop', 'my shop/1.0', 'my-shop/1.0'])->throws(InvalidArgumentException::class);

it('refuses a store URL that could inject a header', function () {
    ClientOptions::fromArray(['api_key' => 'k', 'store_url' => "https://shop.example\r\nX-Evil: 1"]);
})->throws(InvalidArgumentException::class);

it('masks the key for logs', function () {
    expect(ClientOptions::fromArray(['api_key' => 'quoy_live_6ikb795j.abcdefgh9ClA'])->maskedApiKey())
        ->toBe('quoy_live_6ikb795j.****9ClA');
});

it('replaces options without touching the original', function () {
    $original = new QuoyerClient('quoy_live_a.b');
    $copy = $original->withOptions(['api_key' => 'quoy_live_c.d']);
    $store = $original->forStore('https://shop.example.com');

    expect($original->options()->apiKey)->toBe('quoy_live_a.b')
        ->and($copy->options()->apiKey)->toBe('quoy_live_c.d')
        ->and($store->options()->storeUrl)->toBe('https://shop.example.com')
        ->and($original->options()->storeUrl)->toBeNull();
});
