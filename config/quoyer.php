<?php

/*
|--------------------------------------------------------------------------
| Quoyer
|--------------------------------------------------------------------------
|
| Settings for the Quoyer PHP SDK's Laravel integration. Publish with:
|
|     php artisan vendor:publish --tag=quoyer-config
|
*/

return [

    // The merchant's API key (dashboard → Integration → API keys). Keep it in
    // .env, never in version control.
    'api_key' => env('QUOYER_API_KEY'),

    // The Quoyer install. `/api/v1` is added for you.
    'base_url' => env('QUOYER_BASE_URL', 'https://quoyer.com'),

    // Storefronts only: this shop's base URL, sent as X-Quoyer-Store on
    // every call so it counts as a connected store. Leave empty for a
    // back-office integration or a script.
    'store_url' => env('QUOYER_STORE_URL'),

    // Optional `name/version` of your platform, e.g. `myshop/2.1.0`. It names
    // the platform of a connected store in the merchant's dashboard.
    'platform' => env('QUOYER_PLATFORM'),

    'timeout' => (float) env('QUOYER_TIMEOUT', 10),

    'connect_timeout' => (float) env('QUOYER_CONNECT_TIMEOUT', 3),

    // Retries for connection errors, 502/503/504 and short 429s. Writes that
    // are not idempotent are never retried.
    'max_retries' => (int) env('QUOYER_MAX_RETRIES', 2),

    // The longest Retry-After (seconds) to sleep through on a 429.
    'max_retry_wait' => (int) env('QUOYER_MAX_RETRY_WAIT', 5),

    'webhook' => [
        // The endpoint's signing secret, shown once in the dashboard.
        'secret' => env('QUOYER_WEBHOOK_SECRET'),

        // Seconds a delivery's timestamp may differ from this server's clock.
        'tolerance' => (int) env('QUOYER_WEBHOOK_TOLERANCE', 300),
    ],

];
