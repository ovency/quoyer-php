<h1>
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset=".github/assets/wordmark-dark.svg">
    <img src=".github/assets/wordmark-light.svg" alt="quoyer." height="56">
  </picture>
  <br>
  PHP SDK
</h1>

[![CI](https://github.com/ovency/quoyer-php/actions/workflows/ci.yml/badge.svg)](https://github.com/ovency/quoyer-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

The official PHP client for the [Quoyer](https://quoyer.com) loyalty API: enrol
customers, award and reverse points, redeem them as coupons, read balances and
history, and verify webhooks. Works in any PHP 8.2+ project, with a Laravel
service provider, facade and webhook middleware included.

```php
$quoyer = new Quoyer\QuoyerClient(getenv('QUOYER_API_KEY'));

$quoyer->customers->upsert([
    'external_id'     => '1042',
    'external_source' => 'myshop',
    'email'           => 'jane@example.com',
]);

$result = $quoyer->points->credit([
    'customer_external_id'     => '1042',
    'customer_external_source' => 'myshop',
    'rule_type'                => 'purchase',
    'source_reference'         => 'myshop_order_5531',
    'metadata'                 => ['amount' => 49.99, 'currency' => 'EUR'],
]);

echo $result->pointsAwarded(); // 49
```

**SDK 1.0 covers API contract v1.9 in full.** The complete API reference is at
[quoyer.com/api/docs](https://quoyer.com/api/docs).

---

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Laravel](#laravel)
- [Five things to know first](#five-things-to-know-first)
- [Usage](#usage)
- [Errors](#errors)
- [Retries, timeouts and rate limits](#retries-timeouts-and-rate-limits)
- [Pagination](#pagination)
- [Webhooks](#webhooks)
- [Reading response headers](#reading-response-headers)
- [Calling endpoints newer than the SDK](#calling-endpoints-newer-than-the-sdk)
- [Testing your integration](#testing-your-integration)
- [Versioning](#versioning)
- [More documentation](#more-documentation)

## Requirements

- PHP 8.2 or newer, with `ext-json`
- Guzzle 7 (installed with the SDK; Laravel already ships it)
- A Quoyer API key: the merchant creates one in the dashboard under
  **Integration → API keys**. It is shown once.

## Installation

```bash
composer require quoyer/quoyer-php
```

## Configuration

```php
use Quoyer\QuoyerClient;

// Just the key: talks to https://quoyer.com.
$quoyer = new QuoyerClient('quoy_live_…');

// Or with options.
$quoyer = new QuoyerClient([
    'api_key'   => getenv('QUOYER_API_KEY'),
    'base_url'  => 'https://quoyer.com',
    'store_url' => 'https://shop.example.com',
    'platform'  => 'myshop/2.1.0',
]);
```

| Option | Default | What it does |
|---|---|---|
| `api_key` | *required* | The merchant's key, `quoy_live_…` or `quoy_sandbox_…`. Keep it server-side: never in a browser, an app bundle or version control. |
| `base_url` | `https://quoyer.com` | The Quoyer install. `/api/v1` is added for you. Must be `https`, except on `localhost` and `*.test` hosts. |
| `store_url` | none | **Storefronts only.** Sent as `X-Quoyer-Store` on every call, which registers the shop as a *connected store* on the merchant's plan. Leave it out for a back-office integration or a script: those are never counted. See [Connected stores](docs/concepts.md#connected-stores). |
| `platform` | none | `name/version` of your platform. Shown as the connected store's platform in the merchant's dashboard. |
| `timeout` | `10` | Seconds to wait for a whole response. |
| `connect_timeout` | `3` | Seconds to wait for a connection. A storefront should never hold a shopper's checkout for long. |
| `max_retries` | `2` | Retries after the first attempt. See [Retries](#retries-timeouts-and-rate-limits). |
| `max_retry_wait` | `5` | The longest `Retry-After` (seconds) the client sleeps through on a 429. |

A second argument takes your own Guzzle client (proxies, custom TLS, test
mocks): `new QuoyerClient($options, $guzzle)`.

**Several merchants in one app?** Derive a client per merchant; the copies
share one HTTP client:

```php
$client = $quoyer->withOptions(['api_key' => $merchant->quoyer_api_key]);
$shop   = $quoyer->forStore('https://second-shop.example.com');
```

## Laravel

The service provider and the `Quoyer` facade are auto-discovered. Add your key
to `.env`:

```dotenv
QUOYER_API_KEY=quoy_live_…
QUOYER_BASE_URL=https://quoyer.com
QUOYER_STORE_URL=                 # storefronts only
QUOYER_WEBHOOK_SECRET=whsec_…     # if you receive webhooks
```

Then inject the client, or use the facade:

```php
use Quoyer\QuoyerClient;
use Quoyer\Laravel\Facades\Quoyer;

public function __construct(private QuoyerClient $quoyer) {}

Quoyer::customers()->findByEmail('jane@example.com');
```

Publish the config file to change more (`php artisan vendor:publish --tag=quoyer-config`).
Webhooks get a ready-made middleware: see [docs/laravel.md](docs/laravel.md).

## Five things to know first

1. **Identify customers by your own id.** Send `external_id` + `external_source`
   (your platform's name, e.g. `myshop`) on every upsert, and
   `customer_external_id` + `customer_external_source` on credits and
   redemptions. You never have to store Quoyer's `cus_` id. In a physical
   store, `customer_card_number` or `customer_phone` work too.
2. **Upsert before you credit.** `customers->upsert()` creates or updates;
   call it before every credit so the customer exists.
3. **Every write has a `source_reference`, and it makes the write safe to
   repeat.** Build one stable reference per business event
   (`myshop_order_5531`) and send it every time. A repeat returns the original
   result (`wasNew()` is false), so a retry after a timeout can never award or
   spend twice.
4. **Branch on error codes, not messages.** `$e->getErrorCode()` is stable;
   messages change. Account-state messages are safe to show a shopper;
   `getAdminReason()` never is.
5. **Money in `*_minor_units` is always hundredths**, for every currency,
   zero-decimal ones included: ¥1,500 is `150000`. Amounts you *send* in
   `metadata.amount` are plain decimals (`49.99`).

More in [docs/concepts.md](docs/concepts.md).

## Usage

Every service is a property of the client (and a method, for the facade).
Parameters are arrays with the API's own field names, so everything in the
[API reference](https://quoyer.com/api/docs) works as documented. Enums
(`Quoyer\Enums\RuleType::Purchase`) and `DateTimeInterface` values are
accepted wherever a string is.

### Account

```php
$me = $quoyer->me();                 // call it first: it confirms the key

$me->tenant->name;                   // "Example Cosmetics"
$me->accountState();                 // trial, active, past_due, suspended, cancelled
$me->hasFeature('referrals');        // read entitlements, never plan names
$me->limit('members_active');        // int, or null = unlimited
```

### Customers

```php
$customer = $quoyer->customers->upsert([
    'external_id'     => '1042',
    'external_source' => 'myshop',
    'email'           => 'jane@example.com',
    'first_name'      => 'Jane',
    'birthday'        => '1991-04-29',   // Quoyer awards birthday points itself
    'locale'          => 'ro',           // the language of Quoyer's emails to her
    'referral_code'   => $codeFromLink,  // a new customer who arrived by a referral link
]);

$customer->wasCreated();             // true for a new customer (201)
$customer->points();                 // spendable balance
$customer->balance->value?->amount;  // "3.49", in the programme's default currency
$customer->card_number;              // the Quoyer card number

$quoyer->customers->retrieve('cus_…');               // with lifetime totals
$quoyer->customers->findByExternalId('1042', 'myshop');
$quoyer->customers->findByEmail('jane@example.com');
$quoyer->customers->findByCardNumber('4821773019');
$quoyer->customers->update('cus_…', ['first_name' => 'Janet']);
$quoyer->customers->transactions('cus_…');           // the ledger, for an activity feed
$quoyer->customers->buckets('cus_…', ['status' => 'active']); // "your points expire on …"
$quoyer->customers->referrals('cus_…');
$quoyer->customers->delete('cus_…');                 // permanent erasure (GDPR)
```

### Points

```php
use Quoyer\Enums\RuleType;

// An order.
$result = $quoyer->points->credit([
    'customer_external_id'     => '1042',
    'customer_external_source' => 'myshop',
    'rule_type'                => RuleType::Purchase,
    'source_reference'         => 'myshop_order_5531',
    'metadata' => [
        'amount'     => 49.99,              // the total paid
        'currency'   => 'EUR',
        'line_items' => [                   // lets rules target products, categories, brands
            ['product_id' => '77', 'category_ids' => ['5', '12'], 'amount' => 40.00, 'quantity' => 2],
            ['product_id' => '81', 'category_ids' => ['3'], 'amount' => 9.99],
        ],
    ],
]);

$result->pointsAwarded();    // across the base rule and any stacking bonuses
$result->wasNew();           // false on a repeat of the same source_reference
$result->note;               // set when zero points were awarded, e.g. below the minimum

// The order was refunded: take the points back.
$reversal = $quoyer->points->reverseCredit([
    'customer_external_id'     => '1042',
    'customer_external_source' => 'myshop',
    'source_reference'         => 'myshop_order_5531',
    'reason'                   => 'refund',
    'triggered_by'             => ['order_id' => 5531],
]);
$reversal->wasNewlyReversed();   // false if it had been reversed already (still a success)
```

Signup, newsletter and review bonuses use `RuleType::Signup`,
`NewsletterSignup` and `Review` with their own `source_reference`
(`myshop_signup_1042`). Never credit `birthday` or `referral`: Quoyer does.

### Redemptions

```php
$redemption = $quoyer->redemptions->create([
    'customer_external_id'     => '1042',
    'customer_external_source' => 'myshop',
    'points'                   => 100,
    'generate_coupon_code'     => true,
    'source_reference'         => 'myshop_cart_7_attempt_1',
]);

$redemption->coupon_code;        // "QYR-516688D8": create the real coupon on your shop
$redemption->monetary_value;     // "1.00" in $redemption->currency

// The order was placed: record it on the redemption.
$quoyer->redemptions->mergeMetadata($redemption->id, ['order_id' => 5532]);

// The coupon was removed or the order refunded: give the points back.
$quoyer->redemptions->reverse($redemption->id, ['reason' => 'coupon removed']);
```

The limits per redemption are on the programme: `$quoyer->program->retrieve()->minimum_redemption_points`.

### Everything else

```php
$quoyer->buckets->list(['customer_id' => 'cus_…', 'status' => 'active']);
$quoyer->earningRules->list(['include_inactive' => true]);
$quoyer->earningRules->create(['name' => 'Double points on skincare', 'type' => 'purchase', 'stacks' => true, 'points_multiplier' => 200, 'target_category_ids' => ['5']]);
$quoyer->catalogue->sync($allProductsCategoriesAndBrands);   // batches of 1,000 for you
$quoyer->currencies->create(['currency_code' => 'RON', 'currency_units_per_point' => 1000]);
$quoyer->program->retrieve();
$quoyer->tiers->list()->isActive();                         // show nothing about tiers when false
```

Every method, with the endpoint it calls: [docs/reference.md](docs/reference.md).

### Reading objects

Responses are read-only objects. Read fields as properties or array keys; an
absent field reads as `null`. Nested objects are typed by their `object` field.

```php
$customer->email;
$customer['email'];
$customer->tier?->name;
$customer->toArray();        // exactly what the API sent
json_encode($customer);      // the same
```

Fields added to the API after this SDK was released are kept and readable:
the SDK never drops what the API sends.

## Errors

Every exception implements `Quoyer\Exceptions\QuoyerException`.

| Exception | When |
|---|---|
| `AuthenticationException` | 401: missing, invalid or revoked key. Stop and ask the merchant for a key. |
| `AccountStateException` | 403: the account refuses this call (redemption paused, read-only, programme paused or closed, store limit). **`getMessage()` is shopper-safe.** |
| `NotFoundException` | 404: unknown id, `customer_not_found`, `bucket_not_found`. |
| `ValidationException` | 422: a field failed validation. `getErrors()` has every field. |
| `InvalidRequestException` | Any other refusal: `insufficient_points`, `email_conflict`, `member_limit_reached`, `no_active_rule`, `feature_not_in_plan`… (parent of the two above). |
| `RateLimitException` | 429 beyond `max_retry_wait`. `getRetryAfter()` says how long. |
| `ConfigurationException` | Something is not set up: `unconfigured_currency`, or a Quoyer-side problem. |
| `ConnectionException` | Quoyer could not be reached. Repeat the call with the same `source_reference`. |
| `UnexpectedResponseException` | Not the API's answer (HTML, a redirect): usually a wrong `base_url`. |
| `InvalidArgumentException` | The SDK was called wrongly (no key, empty id, no `source_reference`). Nothing was sent. |

```php
use Quoyer\ErrorCode;
use Quoyer\Exceptions\AccountStateException;
use Quoyer\Exceptions\InvalidRequestException;

try {
    $quoyer->redemptions->create([...]);
} catch (AccountStateException $e) {
    $shopperMessage = $e->getMessage();          // safe to show
    $logger->warning($e->getAdminReason());       // never to the shopper
} catch (InvalidRequestException $e) {
    match ($e->getErrorCode()) {
        ErrorCode::INSUFFICIENT_POINTS    => /* offer fewer points */,
        ErrorCode::INVALID_REDEEM_REQUEST => /* outside the programme's min/max */,
        default                           => throw $e,
    };
}
```

`ErrorCode` has a constant for every code the API sends. The full list, with
what to do about each: [docs/errors.md](docs/errors.md).

## Retries, timeouts and rate limits

The client retries for you, and only when it is safe:

- **Connection errors and 502/503/504** are retried up to `max_retries` times
  with exponential backoff and jitter (about 0.5 s, then 1 s), for every read
  and every write the API makes idempotent: upserts, credits, reversals,
  redemptions, updates. **Creating an earning rule or a currency, deleting a
  customer, and an upsert carrying a `referral_code` are never retried**,
  because a lost answer followed by a retry could do them twice.
- **429 Too Many Requests** is retried for any call (the API refused it before
  doing anything), but only when `Retry-After` is at most `max_retry_wait`
  seconds. A longer wait throws `RateLimitException` at once: a storefront
  should not hold a shopper for a minute.

Limits are per merchant, shared by all their keys, per minute: reads get the
plan's `api_per_min`, writes a fifth of it.

## Pagination

Lists come oldest first, 20 per page by default (up to 100).

```php
// One page.
$page = $quoyer->customers->list(['limit' => 100]);
foreach ($page as $customer) { … }
$page->hasMore();
$next = $page->nextPage();          // null on the last page

// Every item, fetching pages only as you reach them.
foreach ($quoyer->customers->all(['external_source' => 'myshop']) as $customer) { … }
```

## Webhooks

Quoyer can POST events to your URL: customer created/updated/deleted, points
credited or reversed, redemptions, tier changes, referral rewards, expiries.
Always verify the signature against the **raw** body:

```php
use Quoyer\Enums\EventType;
use Quoyer\Exceptions\SignatureVerificationException;
use Quoyer\Webhook;

try {
    $event = Webhook::constructEvent(
        file_get_contents('php://input'),
        $_SERVER['HTTP_X_QUOYER_SIGNATURE'] ?? '',
        getenv('QUOYER_WEBHOOK_SECRET'),
    );
} catch (SignatureVerificationException) {
    http_response_code(400);
    exit;
}

match ($event->typeEnum()) {
    EventType::RedemptionCreated => handleRedemption($event->data->redemption),
    EventType::PointsExpired     => notifyExpiry($event->data->customer, $event->data->points_expired),
    default                      => null,   // unknown types: ignore, still answer 2xx
};

http_response_code(200);
```

Deduplicate on `$event->id`: it is the same on every retry. In Laravel, use the
`quoyer.webhook` middleware instead. Details: [docs/webhooks.md](docs/webhooks.md).

## Reading response headers

Every object keeps the response it came from:

```php
$response = $customer->getLastResponse();

$response->rateLimit()?->remaining;     // calls left this minute
$response->isPaymentPastDue();          // for the merchant's admin, never for shoppers
$response->deprecation();               // fields that will be removed
$response->statusCode;
```

## Calling endpoints newer than the SDK

If the API gains an endpoint before the SDK does, call it directly. You get
the same authentication, headers and exceptions:

```php
$response = $quoyer->request('GET', '/new-endpoint', ['limit' => 10]);   // any path under /api/v1
$response->json;
```

Pass `retryable: true` only for a write that is safe to repeat.

## Testing your integration

Pass a Guzzle client with a mock handler to test without the network:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

$mock = new MockHandler([new Response(200, [], json_encode(['object' => 'program', 'name' => 'Test']))]);
$quoyer = new QuoyerClient('quoy_sandbox_test', new Client(['handler' => HandlerStack::create($mock)]));
```

Sign test webhooks with `Webhook::generateSignatureHeader($body, $secret)`.

For end-to-end tests, use a **test merchant's** key against staging.

## Versioning

The SDK follows [Semantic Versioning](https://semver.org). Each release names
the API contract version it covers (`QuoyerClient::API_CONTRACT`).

- A new field in the API needs no SDK release: unknown fields are kept.
- A new endpoint or parameter arrives in a **minor** release.
- A breaking change in the API, which Quoyer announces in advance, arrives in
  a **major** release.

Changes are listed in [CHANGELOG.md](CHANGELOG.md).

## More documentation

- [docs/concepts.md](docs/concepts.md): customers, idempotency, money, account states, connected stores
- [docs/recipes.md](docs/recipes.md): an online shop's order flow, checkout redemption, an in-store till, a loyalty page
- [docs/laravel.md](docs/laravel.md): configuration, facade, webhook middleware, queues
- [docs/webhooks.md](docs/webhooks.md): events, signatures, retries and replay
- [docs/errors.md](docs/errors.md): every error code and what to do
- [docs/reference.md](docs/reference.md): every SDK method and the endpoint it calls
- [docs/maintaining.md](docs/maintaining.md): keeping the SDK in step with the API, releasing
- [API reference](https://quoyer.com/api/docs): the contract itself

## Security

Report vulnerabilities privately to **support@quoyer.com** (subject: "Security"), not in a public
issue. See [SECURITY.md](SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
