# Concepts

What you need to know about the Quoyer API to use the SDK well. The contract
itself is at [quoyer.com/api/docs](https://quoyer.com/api/docs).

## The model in one paragraph

Customers earn **points**. Every credit creates a **point bucket**: one batch
of points with the rule that awarded it and its expiry. Every spend creates a
**redemption**, which records which buckets it drained (oldest first). The
wallet ledger (`customers->transactions()`) is the source of truth for the
balance; buckets and redemptions are the story behind it. **Earning rules**
say how points are awarded, **currencies** how money converts to points, and
the **program** holds the settings above both.

## Identifying customers

A customer can be named four ways. Use the first that fits:

| You have | Send |
|---|---|
| Your own customer id | `external_id` + `external_source` on upsert; `customer_external_id` + `customer_external_source` on credits, reversals and redemptions |
| Quoyer's id | `customer_id` (`cus_…`) |
| A scanned or typed card (in store) | `customer_card_number` |
| A phone number (in store) | `customer_phone`, in any typed form |

`external_source` is your platform's name (`myshop`, `woocommerce`). Keep it
constant: `("1042", "myshop")` and `("1042", "MyShop")` are different people.

**Upsert matching**, in order: `(external_id, external_source)`, then `email`,
then `phone`, otherwise a new customer (it needs an email or a phone). A guest
who used the same email before, without an external id, is merged in with
their points. An email that belongs to a different registered customer is
`email_conflict`: the API never guesses.

## Idempotency: `source_reference`

Every credit, reversal and redemption carries a `source_reference`: one stable
string per business event, which you build.

| Event | Reference |
|---|---|
| An order | `myshop_order_{order_id}` (add the shop id on a multi-shop platform) |
| A signup bonus | `myshop_signup_{customer_id}` |
| A newsletter bonus | `myshop_newsletter_{customer_id}` |
| A review | `myshop_review_{product_id}_{customer_id}` (one reward per product per customer) |
| A redemption at checkout | `myshop_cart_{cart_id}_{attempt}`: one per ATTEMPT, so a new try after a reversal is a new redemption |
| A till sale | `{location}_{till}_{receipt_number}` |

Sending the same reference again returns the original result with `wasNew()`
false. Two identical requests racing each other both succeed with one result.
That is what makes retries safe, so **never generate a random reference per
call**: a retry would then award twice.

## Money

- **Minor units are hundredths of the currency unit, for every currency**,
  zero-decimal ones included: €12.50 is `1250`, ¥1,500 is `150000`. This
  applies to every `*_minor_units` field.
- Money in responses is either integer minor units or a two-decimal string
  (`"12.50"`), never a float.
- `metadata.amount` on a purchase credit is the order total PAID (tax and
  shipping included) as a plain decimal: `49.99`.
- `customer->balance->value` is in the programme's **default** currency.

## Earning rules

A credit names a rule type (usual) or a rule id. With a type, the
highest-priority active **base** rule of that type applies, plus every active
**stacking** rule of that type as a bonus: each writes its own bucket, all
returned in `buckets`, and `points_awarded` is the total.

Rules can target products, categories or brands. A targeted rule pays only on
matching `metadata.line_items`, and pays nothing when no line items are sent.
Send every category a product is in, not just its default one.

Zero points (below the rule's minimum amount, no matching lines) is a success
with a `note`, not an error.

## Account states

Quoyer never holds a merchant's customers hostage: **balances are never
reduced or hidden by a billing event.** What keeps working:

| Account state | Reads | Earning and reversals | Redeem | Admin writes |
|---|---|---|---|---|
| trial, active | yes | yes | yes | yes |
| past due | yes (`isPaymentPastDue()`) | yes | yes | yes |
| suspended | yes | **yes** | `redemption_unavailable` | `account_read_only` |
| cancelled | 30 days | `program_closed` | `program_closed` | `program_closed` |
| not yet paid | `account_pending` | `account_pending` | `account_pending` | `account_pending` |

"Earning and reversals" includes upserting customers, reversing redemptions,
merging redemption metadata and the catalogue. The merchant can also pause
the programme: credits and redemptions then answer `program_paused`.

These refusals throw `AccountStateException`. Its message is written for a
shopper; `getAdminReason()` is for the merchant and your logs only. **Never
show a shopper anything about billing.**

## Connected stores

A *connected store* is one installed storefront. A storefront sends its base
URL on every call (`store_url` in the SDK, the `X-Quoyer-Store` header):

- The first call from a new store URL registers it if the plan has room
  (`me()->stores->count` / `->limit`). At the limit, a **new** store gets
  `store_limit_reached`; stores already connected keep working.
- Call `me()` from your connection screen: that is where a new store first
  meets the limit, and where to show the merchant `getAdminReason()`.
- A back-office integration, a script or a till app sends **no** store URL
  and is never counted.
- The URL is identified by host and path, lowercased, without scheme, port,
  `www.` or a trailing slash. On a multishop, send each shop's own URL
  (`$quoyer->forStore($url)`); each counts.

## Entitlements

Read what a merchant's plan allows from `me()`, never from a plan name:

```php
$me->hasFeature('vip_tiers');     // unknown or missing = off
$me->limit('members_active');     // null = unlimited; missing = 0
```

When `tiers->list()->isActive()` is false, show nothing about tiers.
Referral endpoints answer `feature_not_in_plan` on plans without referrals.

## "Powered by Quoyer"

Plugins and storefront widgets must show a small "Powered by Quoyer" link to
`me()->brand->powered_by_url`, on every plan. The merchant's own brand kit
(`logo_url`, `accent`…) is in `me()->brand`.
