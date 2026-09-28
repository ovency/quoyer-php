# Recipes

Complete flows, in the order they happen. Every snippet assumes
`$quoyer = new Quoyer\QuoyerClient([...])`.

## An online shop: earning on orders

**When a customer registers or updates their account:**

```php
$quoyer->customers->upsert([
    'external_id'     => (string) $user->id,
    'external_source' => 'myshop',
    'email'           => $user->email,
    'first_name'      => $user->first_name,
    'last_name'       => $user->last_name,
    'birthday'        => $user->birthday?->format('Y-m-d'),
    'locale'          => app()->getLocale(),
    'referral_code'   => session('quoyer_ref'),   // captured from ?ref= on arrival
]);

// Optional signup bonus. Its reference makes it once per customer, forever.
$quoyer->points->credit([
    'customer_external_id'     => (string) $user->id,
    'customer_external_source' => 'myshop',
    'rule_type'                => 'signup',
    'source_reference'         => "myshop_signup_{$user->id}",
]);
```

A signup bonus with no active signup rule throws `InvalidRequestException`
`no_active_rule`: catch it and carry on, the merchant simply has none.

**When an order is paid** (not when it is placed):

```php
$result = $quoyer->points->credit([
    'customer_external_id'     => (string) $order->user_id,
    'customer_external_source' => 'myshop',
    'rule_type'                => 'purchase',
    'source_reference'         => "myshop_order_{$order->id}",
    'occurred_at'              => $order->paid_at,
    'metadata' => [
        'amount'     => $order->total_paid,      // tax and shipping included
        'currency'   => $order->currency,
        'order_id'   => $order->id,
        'line_items' => $order->lines->map(fn ($line) => [
            'product_id'   => (string) $line->product_id,
            'category_ids' => $line->product->categories->pluck('id')->map('strval')->all(),
            'brand_id'     => $line->product->brand_id,
            'amount'       => $line->total,       // the LINE total, quantity applied
            'quantity'     => $line->quantity,
        ])->all(),
    ],
]);
```

Do it from a queued job, after the database commit, so a slow or unreachable
Quoyer never holds the order: the `source_reference` makes the job safe to
retry.

**When an order is refunded or cancelled:**

```php
try {
    $quoyer->points->reverseCredit([
        'customer_external_id'     => (string) $order->user_id,
        'customer_external_source' => 'myshop',
        'source_reference'         => "myshop_order_{$order->id}",
        'reason'                   => 'refund',
        'triggered_by'             => ['order_id' => $order->id, 'status' => 'refunded'],
    ]);
} catch (\Quoyer\Exceptions\NotFoundException $e) {
    // bucket_not_found: nothing was ever credited for this order. Done.
}
```

This takes back every bucket the order produced, VIP-tier bonuses and the
referral payouts it triggered included: do nothing extra. The API reverses a
credit whole; for a partial refund, reverse and credit the kept amount under
a new reference (`myshop_order_{id}_r{refund_id}`).

## Checkout: paying with points

```php
$program  = $quoyer->program->retrieve();           // cache for a few minutes
$customer = $quoyer->customers->findByExternalId((string) $user->id, 'myshop');

$spendable = $customer?->points() ?? 0;
$min = $program->minimum_redemption_points ?? 1;
$max = min($spendable, $program->maximum_redemption_points ?? PHP_INT_MAX);
// Offer a choice between $min and $max, or nothing if $max < $min.
```

**The shopper applies points:**

```php
$attempt = $cart->quoyer_attempt + 1;

$redemption = $quoyer->redemptions->create([
    'customer_external_id'     => (string) $user->id,
    'customer_external_source' => 'myshop',
    'points'                   => $points,
    'currency'                 => $cart->currency,
    'generate_coupon_code'     => true,
    'source_reference'         => "myshop_cart_{$cart->id}_{$attempt}",
    'metadata'                 => ['cart_id' => $cart->id],
]);

// Your shop creates the actual discount, worth $redemption->monetary_value.
$cart->applyDiscount($redemption->coupon_code, $redemption->monetary_value);
$cart->update(['quoyer_redemption_id' => $redemption->id, 'quoyer_attempt' => $attempt]);
```

**The order is placed:**

```php
$quoyer->redemptions->mergeMetadata($cart->quoyer_redemption_id, [
    'order_id'        => $order->id,
    'order_reference' => $order->number,
]);
```

**The shopper removes the discount, abandons the cart, or the order is refunded:**

```php
$quoyer->redemptions->reverse($cart->quoyer_redemption_id, [
    'reason'       => 'coupon removed',
    'triggered_by' => ['cart_id' => $cart->id],
]);
```

Reversal puts the points back into the exact buckets they came from, keeping
their expiry. It is idempotent, and it works while the merchant's account is
suspended.

**Handle the refusals the shopper can meet:**

```php
use Quoyer\ErrorCode;
use Quoyer\Exceptions\AccountStateException;
use Quoyer\Exceptions\InvalidRequestException;

try {
    // create() as above
} catch (AccountStateException $e) {
    return back()->withErrors($e->getMessage());       // shopper-safe
} catch (InvalidRequestException $e) {
    return back()->withErrors(match ($e->getErrorCode()) {
        ErrorCode::INSUFFICIENT_POINTS    => 'You don\'t have enough points for that.',
        ErrorCode::INVALID_REDEEM_REQUEST => 'Choose an amount within the limits shown.',
        default                           => 'Points can\'t be used right now.',
    });
}
```

## In store: a till

A till identifies the member by card number (scanned or typed) or phone, and
names the location (`loc_…`, from the merchant's Locations page). It sends no
`store_url`: a till is not a connected storefront.

```php
$quoyer = new QuoyerClient(['api_key' => $key, 'timeout' => 5]);

$member = $quoyer->customers->findByCardNumber($scanned)
       ?? $quoyer->customers->findByPhone($typedPhone);

// Enrol on the spot, by phone.
$member ??= $quoyer->customers->upsert(['phone' => $typedPhone, 'first_name' => $name]);

// Award for a sale.
$quoyer->points->credit([
    'customer_card_number' => $member->card_number,
    'location_id'          => 'loc_…',
    'rule_type'            => 'purchase',
    'source_reference'     => "shop12_till3_{$receiptNumber}",
    'metadata'             => ['amount' => 23.40, 'currency' => 'RON', 'receipt' => $receiptNumber],
]);

// Spend.
$quoyer->redemptions->create([
    'customer_card_number' => $member->card_number,
    'location_id'          => 'loc_…',
    'points'               => 200,
    'source_reference'     => "shop12_till3_{$receiptNumber}_spend",
]);
```

`location_id` limits the earning rules to that location's and records where
the sale happened. An unknown or inactive location throws `location_not_found`.

The SDK does not queue calls made without a connection. If the till is
offline, keep the calls with their `source_reference` and send them when it
is back: they are idempotent, so a call that did reach Quoyer before the
connection dropped is not applied twice.

## A "my loyalty" page

```php
$customer = $quoyer->customers->findByExternalId((string) $user->id, 'myshop');
$full     = $quoyer->customers->retrieve($customer->id);   // adds lifetime totals
$tiers    = $quoyer->tiers->list();

$page = [
    'points'     => $full->points(),
    'worth'      => $full->balance->value?->amount,          // default currency
    'earned'     => $full->lifetime->earned_points,
    'redeemed'   => $full->lifetime->redeemed_points,
    'tier'       => $tiers->isActive() ? $full->tier?->name : null,
    'to_next'    => $tiers->isActive() ? $full->tier_progress?->points_needed : null,
    'share_link' => $full->referral_code ? url('/?ref='.$full->referral_code) : null,
    'expiring'   => $quoyer->customers->buckets($full->id, [
        'status'         => 'active',
        'expires_before' => now()->addDays(30),
    ])->data(),
    'activity'   => $quoyer->customers->transactions($full->id, ['limit' => 20])->data(),
];
```

Label activity rows by `kind`: `earn`, `spend`, `refund`, `expiry`,
`clawback`, `deduction`, `adjustment`. Don't parse `description`.

## Keeping the catalogue in sync

So the merchant can target earning rules by product, category and brand name:

```php
$quoyer->catalogue->sync((function () {
    foreach (Category::cursor() as $category) {
        yield ['kind' => 'category', 'id' => $category->id, 'name' => $category->name, 'parent_id' => $category->parent_id];
    }
    foreach (Brand::cursor() as $brand) {
        yield ['kind' => 'brand', 'id' => $brand->id, 'name' => $brand->name];
    }
    foreach (Product::cursor() as $product) {
        yield ['kind' => 'product', 'id' => $product->id, 'name' => $product->name, 'url' => $product->url, 'image_url' => $product->image_url];
    }
})());
```

Send the whole catalogue each time, nightly or on change: nothing needs
deleting first, and a stale catalogue never changes what anyone earns.
