# Webhooks

Quoyer can POST events to a URL the merchant configures in the dashboard
(**Integration → Webhooks**). Receiving them is optional: everything in them
can be read back from the API.

## Events

| Type | `data` | Sent when |
|---|---|---|
| `customer.created` | `customer` | A customer is created. |
| `customer.updated` | `customer` | A customer is updated. |
| `customer.deleted` | `customer` (id, external ids, email) | A customer is erased. A replayed copy carries `email: "[redacted]"`. |
| `customer.tier_changed` | `customer`, `from`, `to`, `direction` (`up`, `down`, `off`), `qualifying_points`, `at` | A member moves on the VIP ladder: up at once, down in the nightly run. |
| `referral.rewarded` | `referral` | A referral pays. Its reversal arrives as `point_credit.reversed`. |
| `point_credit.created` | `bucket` | Points are credited (one event per bucket). |
| `point_credit.reversed` | `bucket` | A credit is clawed back (one event per bucket). |
| `redemption.created` | `redemption` | Points are spent. |
| `redemption.reversed` | `redemption` | A redemption is undone. |
| `points.expired` | `customer`, `points_expired`, `balance_after`, `expired_at`, `buckets` | A customer's points expired (one event per customer per nightly run). |

`Quoyer\Enums\EventType` has a case for each. **New types are added without
notice**: `$event->typeEnum()` is null for a type this SDK version doesn't
know. Ignore it and still answer 2xx.

## Verifying and reading

```php
use Quoyer\Enums\EventType;
use Quoyer\Exceptions\SignatureVerificationException;
use Quoyer\Webhook;

$payload = file_get_contents('php://input');      // the RAW body, before any parsing

try {
    $event = Webhook::constructEvent(
        $payload,
        $_SERVER['HTTP_X_QUOYER_SIGNATURE'] ?? '',
        getenv('QUOYER_WEBHOOK_SECRET'),
    );
} catch (SignatureVerificationException) {
    http_response_code(400);
    exit;
}

if ($event->isType(EventType::CustomerTierChanged)) {
    $customer  = $event->data->customer;          // a Quoyer\Resources\Customer
    $direction = $event->data->direction;         // up, down, off
    $newTier   = $event->data->to?->name;
}
```

`constructEvent()` checks, in order: the header is well formed; its timestamp
is within 300 seconds of your clock (change it with the fourth argument);
the HMAC-SHA256 of `"{timestamp}.{raw body}"` with the endpoint's secret
matches, compared in constant time. The secret is shown once when the
endpoint is created or its secret rotated.

`Webhook::isValidSignature()` is the same check as a boolean.

## Delivery rules

- **Success** is any 2xx within 30 seconds. Queue slow work and answer at once.
- **Retries:** 5 attempts in all, 30 seconds, then 2 minutes, 10 minutes and
  1 hour apart.
- **Deduplicate on `$event->id`** (also in `X-Quoyer-Event-Id`): it is the same
  on every retry and replay. `X-Quoyer-Delivery-Id` differs per attempt.
- **Order is not guaranteed.** Use `created_at` and the resources' own
  timestamps, or read the resource back from the API.
- **Auto-disable:** after 20 events in a row fail every attempt, the endpoint is
  switched off and the merchant is emailed. Events during the outage are kept;
  reactivating offers to replay the last 72 hours, oldest first, with their
  original ids.

## Testing

Sign your own test deliveries:

```php
$body   = json_encode(['id' => 'evt_test', 'object' => 'event', 'type' => 'redemption.created', 'data' => [...]]);
$header = Webhook::generateSignatureHeader($body, 'whsec_test');
```

The contract publishes a test vector the SDK's own suite checks: secret
`whsec_test_4f8a2c`, timestamp `1790000000`, body
`{"id":"evt_01TEST","object":"event","type":"customer.created"}` give
`t=1790000000,v1=4c3614a8dee2fe5493c2b446b650906a868553dc8d2d69910fc3c61e0aaa31cd`.

In Laravel, see [laravel.md](laravel.md#receiving-webhooks) for the
`quoyer.webhook` middleware.
