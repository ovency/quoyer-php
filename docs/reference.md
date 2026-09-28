# Reference

Every SDK method, the endpoint it calls (under `/api/v1`), and what it returns.
Parameters are the API's own field names: see the
[API reference](https://quoyer.com/api/docs) for each one.

"Retried" means the SDK repeats the call after a connection error or a
502/503/504. Every call is retried after a short 429.

## Client

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `me()` | `GET /me` | `Resources\Me` | yes |
| `request($method, $path, $query, $body, $retryable)` | any | `Http\ApiResponse` | GET, or when `$retryable` |
| `withOptions(array)` | | a new `QuoyerClient` | |
| `forStore(string)` | | a new `QuoyerClient` | |

## `customers`

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `list(params)` | `GET /customers` | `Collection<Customer>` | yes |
| `all(params)` | `GET /customers`, every page | `Generator<Customer>` | yes |
| `upsert(params)` | `POST /customers` | `Customer` (`wasCreated()`) | yes, unless `referral_code` is sent |
| `retrieve(id)` | `GET /customers/{id}` | `Customer` with `lifetime` | yes |
| `update(id, params)` | `PATCH /customers/{id}` | `Customer` | yes |
| `delete(id)` | `DELETE /customers/{id}` | nothing | **no** |
| `balance(id)` | `GET /customers/{id}/balance` | `Customer` with `lifetime` | yes |
| `transactions(id, params)` | `GET /customers/{id}/transactions` | `Collection<PointTransaction>` | yes |
| `buckets(id, params)` | `GET /customers/{id}/buckets` | `Collection<PointBucket>` | yes |
| `referrals(id, params)` | `GET /customers/{id}/referrals` | `Collection<Referral>` | yes |
| `findByExternalId(id, source)` | `GET /customers?external_id=&external_source=&limit=1` | `?Customer` | yes |
| `findByEmail(email)` | `GET /customers?email=&limit=1` | `?Customer` | yes |
| `findByPhone(phone)` | `GET /customers?phone=&limit=1` | `?Customer` | yes |
| `findByCardNumber(number)` | `GET /customers?card_number=&limit=1` | `?Customer` | yes |

## `points`

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `credit(params)` | `POST /points/credit` | `PointCreditResult` (`pointsAwarded()`, `wasNew()`) | yes (idempotent) |
| `reverseCredit(params)` | `POST /points/credit/reverse` | `PointCreditReversalResult` (`wasNewlyReversed()`); a 409 "already reversed" is returned, not thrown | yes (idempotent) |

## `redemptions`

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `list(params)` / `all(params)` | `GET /redemptions` | `Collection<Redemption>` / `Generator` | yes |
| `retrieve(id)` | `GET /redemptions/{id}` | `Redemption` | yes |
| `create(params)` | `POST /redemptions` | `Redemption` (`wasNew()`) | yes (idempotent) |
| `reverse(id, params)` | `POST /redemptions/{id}/reverse` | `Redemption` (`wasNewlyReversed()`, `points_restored`, `bucket_restorations`) | yes (idempotent) |
| `mergeMetadata(id, metadata)` | `POST /redemptions/{id}/metadata` | `Redemption` | yes (idempotent) |
| `findBySourceReference(ref)` | `GET /redemptions?source_reference=&type=all&limit=1` | `?Redemption` | yes |

## `buckets`

| Method | Endpoint | Returns |
|---|---|---|
| `list(params)` / `all(params)` | `GET /buckets` | `Collection<PointBucket>` / `Generator` |
| `retrieve(id)` | `GET /buckets/{id}` | `PointBucket` |

## `earningRules`

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `list(params)` / `all(params)` | `GET /earning-rules` | `Collection<EarningRule>` / `Generator` | yes |
| `retrieve(id)` | `GET /earning-rules/{id}` | `EarningRule` | yes |
| `create(params)` | `POST /earning-rules` | `EarningRule` | **no** |
| `update(id, params)` | `PATCH /earning-rules/{id}` | `EarningRule` | yes |

## `catalogue`

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `list(params)` / `all(params)` | `GET /catalogue` | `Collection<CatalogueItem>` / `Generator` | yes |
| `upsert(items)` | `POST /catalogue` (1–1,000 items) | `CatalogueSyncResult` | yes |
| `sync(iterable, batchSize)` | `POST /catalogue`, once per batch | `CatalogueSyncResult` (summed) | yes |

## `currencies`

| Method | Endpoint | Returns | Retried |
|---|---|---|---|
| `list(params)` / `all(params)` | `GET /currencies` | `Collection<Currency>` / `Generator` | yes |
| `retrieve(code)` | `GET /currencies/{code}` | `Currency` | yes |
| `create(params)` | `POST /currencies` | `Currency` | **no** |
| `update(code, params)` | `PATCH /currencies/{code}` | `Currency` | yes |

## `program` and `tiers`

| Method | Endpoint | Returns |
|---|---|---|
| `program->retrieve()` | `GET /program` | `Program` |
| `program->update(params)` | `PATCH /program` | `Program` |
| `tiers->list()` | `GET /tiers` | `TierList` (`isActive()`) |

## Webhooks

| Method | Does |
|---|---|
| `Webhook::constructEvent($payload, $header, $secret, $tolerance = 300)` | Verifies and returns `Resources\Event`, or throws `SignatureVerificationException`. |
| `Webhook::verifySignature(...)` | Verifies, or throws. |
| `Webhook::isValidSignature(...)` | Verifies, as a boolean. |
| `Webhook::generateSignatureHeader($payload, $secret, $timestamp = now)` | Signs, for your tests. |

## Objects

All returned objects extend `Quoyer\QuoyerObject`: read-only, fields as
properties or array keys, `toArray()`, `jsonSerialize()`, `has($field)`,
`getLastResponse()`.

`Collection`: `data()`, `first()`, `isEmpty()`, `count()` (this page),
`hasMore()`, `nextCursor()`, `nextPage()`, `autoPagingIterator()`, and
`foreach` over the page.

`Http\ApiResponse`: `statusCode`, `body`, `json`, `header($name)`,
`headers()`, `rateLimit()`, `retryAfter()`, `warning()`,
`isPaymentPastDue()`, `deprecation()`.
