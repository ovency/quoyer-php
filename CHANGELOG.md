# Changelog

All notable changes to `quoyer/quoyer-php`. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
follows [Semantic Versioning](https://semver.org/).

## [1.4.0] - 2026-10-06

Covers Quoyer API contract **v1.17**.

### Added

- `Reward::$summary` (v1.17): the reward in one sentence for a shopper, written by Quoyer
  from the reward's own data ("Spend 500 points, get an extra 10 RON off.", "…get a free
  cookie."). Pass `locale` to `rewards->list()` for the language; without it, the named
  customer's, else English.

## [1.3.0] - 2026-10-06

Covers Quoyer API contract **v1.16**.

### Added

- `PointTransaction::$label` and `PointBucket::$label` (v1.16): what the points were for,
  in words a shopper reads ("Purchase", "Welcome bonus", "Goodwill gesture"), already in
  their language. `earning_rule->label` is the rule itself in the same words. Show these in
  an activity feed in place of the rule's internal `name`.
- `locale` in the `customers->transactions()` parameter shape (v1.16): the language for
  `label`. Without it, the customer's own language, else English.

## [1.2.0] - 2026-10-01

Covers Quoyer API contract **v1.15**.

### Added

- `$quoyer->points->preview([...])` (v1.15): what an order would earn, for "Earn 12 points"
  on a product page or in the cart; adds the named customer's tier bonus. `ResourcesPointsPreview`.

## [1.1.0] - 2026-09-30

Covers Quoyer API contract **v1.14**.

### Added

- `$quoyer->points->awardEvent($customerId, ['event' => …, 'source_reference' => …])`
  (v1.14): pays the merchant's `custom` earning rule for an event it named.
  `ErrorCode::UNKNOWN_EVENT`.
- `RuleType::SocialFollow`, `RuleType::SocialShare`, `RuleType::Custom` and
  `EarningRule::$social_network`, `$social_url`, `$event_key` (v1.14).
- `award_welcome` in the `customers->upsert()` parameter shape (v1.14): pays the
  sign-up rule once per customer.
- `$quoyer->rewards`: `list()` (the catalogue, optionally for a customer),
  `retrieve()` and `redeem()` (a reward for a customer, answered as a
  `Redemption`). `Resources\Reward` with `canRedeemFor()`. Coupons (0-point
  rewards the merchant gives a customer) are listed for their holder, with
  `customer.coupons`.
- `Tier::$purchase_discount_percent` and the customer's `tier.purchase_discount_percent`
  (v1.13): the tier's discount on every purchase, for the shop to apply.
- `Redemption::$reward`: the catalogue reward a redemption redeemed, or null.
- `ErrorCode::REWARD_UNAVAILABLE`, `ErrorCode::REWARD_LIMIT_REACHED`.
- v1.10's partial refund fields (`amount`, `points`, `refund_reference`) in the
  `points->reverseCredit()` parameter shape (they already worked).

## [1.0.2] - 2026-09-28

### Security

- Require a Guzzle without published advisories: `^7.15.2 || ^8.0.1` (was
  `^7.8 || ^8.0`). Older releases carry CVE-2026-59883, CVE-2026-67353/54/55
  and CVE-2026-69245/46 (cookie disclosure and scope, host checks, Referer
  leaks). Composer 2.10+ already refuses them; this makes the SDK refuse them
  on every Composer. CI now installs exactly these minimums and fails if the
  resolved version drifts.

## [1.0.1] - 2026-09-28

### Changed

- Allow Guzzle 8 (`^7.8 || ^8.0`). Laravel 13 and the AWS SDK resolve to
  `guzzlehttp/guzzle` 8, so the `^7.8` pin made the SDK uninstallable beside
  them. The client only uses `ClientInterface`, `RequestOptions` and
  `GuzzleException`, unchanged in 8.x; the suite passes on 7.x and 8.x.

## [1.0.0] - 2026-09-28

Covers Quoyer API contract **v1.9** in full.

### Added

- `QuoyerClient` with services for customers, points, redemptions, buckets,
  earning rules, catalogue, currencies, program and tiers, and `me()`.
- In-store identifiers (`customer_card_number`, `customer_phone`,
  `location_id`) and customer lookup by card number and phone.
- Read-only response objects typed by their `object` field, keeping every
  field the API sends, including ones added after this release.
- Cursor pagination: `list()`, `nextPage()`, and `all()` across every page.
- An exception per error family, `ErrorCode` constants for every code, and
  shopper-safe message handling (`isShopperSafe()`, `getAdminReason()`).
- Automatic retries with backoff for connection errors and 502/503/504 on
  idempotent calls only, and for short 429s.
- `Webhook::constructEvent()` signature verification, checked against the
  contract's published test vector.
- Laravel: auto-discovered service provider, `Quoyer` facade, publishable
  config and the `quoyer.webhook` middleware.
- `request()` for endpoints newer than the SDK.
