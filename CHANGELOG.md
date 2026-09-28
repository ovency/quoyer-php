# Changelog

All notable changes to `quoyer/quoyer-php`. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
follows [Semantic Versioning](https://semver.org/).

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
