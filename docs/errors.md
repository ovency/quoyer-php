# Errors

Every error the API sends is one envelope:

```json
{"error": {"type": "invalid_request", "code": "email_conflict", "message": "…", "field": "email"}}
```

The SDK turns it into an exception (all implement
`Quoyer\Exceptions\QuoyerException`) with:

| Method | |
|---|---|
| `getErrorCode()` | **What you branch on.** Stable. Compare with `Quoyer\ErrorCode` constants. |
| `getMessage()` | English, one line. Shopper-safe only when `isShopperSafe()` is true; otherwise a log line. |
| `getAdminReason()` | Why an account-state refusal or a member cap happened. For the merchant and logs, **never** for a shopper. |
| `getField()` / `getErrors()` | The first failing field / every failing field with its messages (validation). |
| `getHttpStatus()`, `getErrorType()`, `getResponse()` | The rest. |

New codes can appear: always keep a default branch.

## Every code

| Code | HTTP | Exception | Meaning | What to do |
|---|---|---|---|---|
| `missing_credentials` | 401 | Authentication | No key sent. | Send the key. |
| `invalid_credentials` | 401 | Authentication | Key not recognised. | Stop; ask the merchant for a valid key. |
| `revoked_credentials` | 401 | Authentication | Key revoked. | Stop; ask the merchant for a new key. |
| `tenant_not_found` | 401 | Authentication | The key's account no longer exists. | Stop; the integration is orphaned. |
| `tenant_not_identified` | 401 | Authentication | No account could be bound. | Retry once; then contact support. |
| `redemption_unavailable` | 403 | AccountState | Redemption paused (account suspended). | Hide or disable redemption; show the message. Earning continues. |
| `account_read_only` | 403 | AccountState | Account is read-only plus earning. | Don't retry admin calls; reads and earning work. |
| `account_pending` | 403 | AccountState | Account not activated yet. | Wait for the merchant to finish signing up. |
| `program_closed` | 403 | AccountState | The programme has closed. | Stop calling; show the message if a shopper asks. |
| `program_paused` | 403 | AccountState | The merchant switched the programme off. | Don't earn or redeem; reads and reversals work. |
| `store_limit_reached` | 403 | AccountState | This new storefront is over the plan's store limit. | Show the merchant `getAdminReason()` in your admin, never shoppers. |
| `feature_not_in_plan` | 403 | InvalidRequest | The plan lacks this feature (e.g. referrals). | Hide the feature; don't retry. The message is shopper-safe. |
| `too_many_requests` | 429 | RateLimit | Over the per-minute limit. | Wait `getRetryAfter()` seconds. |
| `invalid_request` | 422 | Validation | A field failed validation. | Fix the fields in `getErrors()`. |
| `field_removed` | 422 | Validation | A field the API no longer accepts. | Stop sending it (`getField()`). |
| `resource_not_found` | 404 | NotFound | The id in the path matches nothing. | Check the id. |
| `customer_not_found` | 404 | NotFound | No customer matches the identifiers. | Upsert the customer first. |
| `bucket_not_found` | 404 | NotFound | Nothing was credited for that reference and rule type. | Nothing to reverse: treat as done. |
| `location_not_found` | 422 | InvalidRequest | `location_id` names no active location. | Check the Locations page. |
| `email_conflict` | 422 | InvalidRequest | The email belongs to another customer. | Resolve the duplicate. |
| `phone_conflict` | 422 | InvalidRequest | The phone belongs to another customer. | Resolve the duplicate. |
| `external_id_conflict` | 422 | InvalidRequest | The external id pair belongs to another customer. | Resolve the duplicate. |
| `member_limit_reached` | 422 | InvalidRequest | The plan's member cap refuses a NEW member. | Don't retry; log `getAdminReason()`. The shopper can still shop. |
| `rule_required` | 422 | InvalidRequest | Neither `rule_id` nor `rule_type` usable. | Send one. |
| `rule_not_found` | 422 | InvalidRequest | `rule_id` matches no rule. | Check the id. |
| `invalid_rule_type` | 422 | InvalidRequest | Unknown `rule_type`. | Use a `RuleType` value. |
| `no_active_rule` | 422 | InvalidRequest | No active rule of that type. | Nothing to award; the merchant has no such rule. |
| `invalid_earn_request` | 422 | InvalidRequest | The rule can't award for this request. | Read the message; fix the request or the rule. |
| `unconfigured_currency` | 422 | Configuration | The currency has no active setting. | Send a configured currency, or ask the merchant to add it. |
| `duplicate_source_reference` | 409 | InvalidRequest | A replay the engine couldn't resolve. Not expected. | Treat as already recorded. |
| `invalid_credit_reverse_request` | 422 | InvalidRequest | The reversal was rejected. | Read the message. |
| `insufficient_points` | 422 | InvalidRequest | Not enough spendable points. | Offer fewer points. |
| `invalid_redeem_request` | 422 | InvalidRequest | Outside the programme's min/max, or not positive. | Read the limits from `program->retrieve()`. |
| `invalid_reverse_request` | 422 | InvalidRequest | The redemption can't be reversed. | Don't retry. |
| `currency_already_exists` | 409 | InvalidRequest | Already configured. | Use `currencies->update()`. |
| `invalid_currency_code` | 422 | InvalidRequest | Not an ISO 4217 code. | Fix the code. |
| `program_not_initialized` | 500 | Configuration | The account has no programme record. | Contact support. |
| `account_state_unknown` | 503 | Configuration | The account's state can't be read. | Retried automatically; contact support if it persists. |
| `operation_not_classified` | 503 | Configuration | Misconfigured on Quoyer's side. | Contact support with `getAdminReason()`. |

## Not from the API

| Exception | Meaning |
|---|---|
| `ConnectionException` | DNS, TLS, a refused connection, a timeout, after the retries. Whether a write happened is unknown: repeat it with the same `source_reference`. |
| `UnexpectedResponseException` | HTML, a redirect, an empty body where JSON was due. Almost always a wrong `base_url` or a proxy. |
| `InvalidArgumentException` | The SDK was called wrongly. Nothing was sent. |
| `SignatureVerificationException` | A webhook failed verification. Answer 400. |

## A pattern for storefronts

A loyalty failure must never break a checkout or a registration:

```php
use Quoyer\Exceptions\QuoyerException;

try {
    $quoyer->customers->upsert([...]);
} catch (QuoyerException $e) {
    report($e);   // log it; the shopper carries on
}
```

For earning, queue the call instead (see [laravel.md](laravel.md#calling-quoyer-from-queued-jobs)).
