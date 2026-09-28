# Contributing

Thanks for helping. Issues and pull requests are welcome.

## Setup

```bash
composer install
composer check      # Pint, PHPStan (level 8) and Pest
```

## Rules

- Every change comes with a test. `vendor/bin/pest` must pass.
- Match the API's own names: parameters are the contract's field names, and
  the SDK never renames or drops a field the API sends.
- A new endpoint needs a service method, a row in
  `tests/Contract/OperationsTest.php`, and a line in `docs/reference.md`.
  See [docs/maintaining.md](docs/maintaining.md).
- Run `vendor/bin/pint` before committing.

Security issues: see [SECURITY.md](SECURITY.md), not a public issue.
