# Laravel

The package registers itself: a `QuoyerClient` singleton built from
`config/quoyer.php`, the `Quoyer` facade, and the `quoyer.webhook` middleware.

## Configuration

```dotenv
QUOYER_API_KEY=quoy_live_…
QUOYER_BASE_URL=https://quoyer.com
QUOYER_STORE_URL=                 # storefronts only (see docs/concepts.md#connected-stores)
QUOYER_PLATFORM=                  # optional, e.g. myshop/2.1.0
QUOYER_TIMEOUT=10
QUOYER_CONNECT_TIMEOUT=3
QUOYER_MAX_RETRIES=2
QUOYER_MAX_RETRY_WAIT=5
QUOYER_WEBHOOK_SECRET=whsec_…
QUOYER_WEBHOOK_TOLERANCE=300
```

To edit the file itself: `php artisan vendor:publish --tag=quoyer-config`.

The client is built on first use, so an app without `QUOYER_API_KEY` boots
and runs its tests; only code that actually calls Quoyer fails.

## Using the client

Inject it:

```php
use Quoyer\QuoyerClient;

final class AwardOrderPoints
{
    public function __construct(private QuoyerClient $quoyer) {}

    public function handle(Order $order): void
    {
        $this->quoyer->points->credit([...]);
    }
}
```

Or use the facade. Services are methods on the facade:

```php
use Quoyer\Laravel\Facades\Quoyer;

Quoyer::customers()->upsert([...]);
Quoyer::points()->credit([...]);
Quoyer::me();
```

### Many merchants in one app

Keep the configured client for defaults and derive one per merchant:

```php
$client = app(QuoyerClient::class)->withOptions(['api_key' => $merchant->quoyer_api_key]);
```

Store merchant keys encrypted (`'quoyer_api_key' => 'encrypted'` cast).

## Calling Quoyer from queued jobs

Anything a customer's request doesn't need to wait for (crediting an order,
reversing a refund, syncing the catalogue) belongs in a queued job dispatched
after the database commit:

```php
final class CreditOrder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function __construct(public int $orderId) {}

    public function handle(QuoyerClient $quoyer): void
    {
        $order = Order::findOrFail($this->orderId);

        $quoyer->points->credit([
            'customer_external_id'     => (string) $order->user_id,
            'customer_external_source' => 'myshop',
            'rule_type'                => 'purchase',
            'source_reference'         => "myshop_order_{$order->id}",
            'metadata'                 => ['amount' => $order->total_paid, 'currency' => $order->currency],
        ]);
    }
}

CreditOrder::dispatch($order->id)->afterCommit();
```

The job is safe to retry because of `source_reference`. Let
`ConnectionException`, `RateLimitException` and `ConfigurationException`
fail the attempt so the queue retries it; catch the `InvalidRequestException`
codes you expect (`no_active_rule`, `customer_not_found`) and stop, because
retrying them unchanged won't help.

## Receiving webhooks

Register a route in `routes/api.php` (no CSRF there) with the middleware:

```php
use App\Http\Controllers\QuoyerWebhookController;

Route::post('/webhooks/quoyer', QuoyerWebhookController::class)->middleware('quoyer.webhook');
```

If the route is in `routes/web.php`, exclude it from CSRF verification in
`bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: ['webhooks/quoyer']);
})
```

The middleware verifies `X-Quoyer-Signature` against the raw body with
`QUOYER_WEBHOOK_SECRET`, answers `400` to anything that fails, and hands the
verified event to your controller:

```php
use Illuminate\Http\Request;
use Quoyer\Enums\EventType;
use Quoyer\Resources\Event;

final class QuoyerWebhookController
{
    public function __invoke(Request $request)
    {
        /** @var Event $event */
        $event = $request->attributes->get('quoyer_event');

        // Retries and replays carry the same id: process each event once.
        if (! Cache::add("quoyer-event:{$event->id}", true, now()->addDays(3))) {
            return response()->noContent();
        }

        match ($event->typeEnum()) {
            EventType::CustomerTierChanged => SyncCustomerTier::dispatch($event->toArray()),
            EventType::PointsExpired       => NotifyPointsExpired::dispatch($event->toArray()),
            default                        => null,
        };

        return response()->noContent();
    }
}
```

Answer within 30 seconds: queue the work, answer 2xx. More in
[webhooks.md](webhooks.md).

## Testing

Swap the client for one with a mock handler:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Quoyer\QuoyerClient;

$mock = new MockHandler([
    new Response(201, [], json_encode(['object' => 'point_credit_result', 'points_awarded' => 49, 'was_new' => true, 'bucket' => null, 'buckets' => []])),
]);

$this->app->instance(QuoyerClient::class, new QuoyerClient(
    'quoy_sandbox_test',
    new Client(['handler' => HandlerStack::create($mock)]),
));
```

Test your webhook route with a real signature:

```php
$body = json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => 'points.expired', 'data' => [...]]);

$this->call('POST', '/api/webhooks/quoyer', server: [
    'HTTP_X_QUOYER_SIGNATURE' => \Quoyer\Webhook::generateSignatureHeader($body, config('quoyer.webhook.secret')),
    'CONTENT_TYPE' => 'application/json',
], content: $body)->assertNoContent();
```
