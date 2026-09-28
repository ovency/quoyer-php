# Maintaining the SDK

How the SDK stays in step with the Quoyer API, and how a release is cut.

## The source of truth

The API contract is `docs/openapi.yaml` in the Quoyer application repository,
served at `/api/docs/openapi.yaml` on every install. Quoyer's own test suite
pins it to the running API. The SDK is written against it.

## The drift check

`tests/Contract/OperationsTest.php` lists every operation in the contract, by
`operationId`, with the SDK call that performs it. It checks two things:

1. **Each SDK call hits the right method and path.** Always runs.
2. **The list matches the contract**: no operation in the contract without
   an SDK method, none in the SDK the contract has dropped. It needs the
   contract file:
   - locally, it finds `../quoyer/docs/openapi.yaml` next to this repository;
   - anywhere else, set `QUOYER_OPENAPI_PATH=/path/to/openapi.yaml`;
   - in CI, set the repository variable `QUOYER_OPENAPI_URL` to a Quoyer
     install's `/api/docs/openapi.yaml` and the workflow downloads it.

   Without the file it is skipped, not failed.

Run it after every API change:

```bash
QUOYER_OPENAPI_PATH=../quoyer/docs/openapi.yaml vendor/bin/pest tests/Contract
```

## When the API changes

| API change | SDK work | Release |
|---|---|---|
| A new response field | None: unknown fields are kept and readable. Add it to the resource's `@property-read` docblock for autocompletion. | patch, or with the next release |
| A new request parameter | None to use it (parameters are arrays). Add it to the method's `@param` array shape. | patch |
| A new endpoint | A method on the right service, a row in `OperationsTest`, a test, a line in `docs/reference.md`. Until then, `$quoyer->request()` reaches it. | **minor** |
| A new error code | A constant in `ErrorCode`, a row in `docs/errors.md`. Mapping to an exception class is by type, so it already works. | patch |
| A new webhook event type | A case in `Enums\EventType`, a row in `docs/webhooks.md`. | minor |
| A new `object` type | A resource class, and its entry in `QuoyerObject::TYPES`. | minor |
| A removed or retyped field, a removed endpoint (a contract break) | Whatever it takes. | **major** |

Update `QuoyerClient::API_CONTRACT` to the contract version the release covers.

## Cutting a release

1. `composer check` (Pint, PHPStan level 8, Pest) passes, with the contract
   check running.
2. Optionally run the live suite against a staging install with a TEST merchant's key:
   `QUOYER_TEST_API_KEY=… QUOYER_TEST_BASE_URL=https://your-staging-install vendor/bin/pest tests/Integration`
   (`QUOYER_TEST_WRITES=1` also creates and deletes a customer).
3. Set `QuoyerClient::VERSION`, add the `CHANGELOG.md` entry.
4. Commit, tag `vX.Y.Z`, push the tag. Packagist picks up the tag once the
   package is registered there.
