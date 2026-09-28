<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Generator;
use Quoyer\Collection;
use Quoyer\Resources\Redemption;

/**
 * Spending points: `$quoyer->redemptions`.
 */
final class RedemptionService extends AbstractService
{
    /**
     * A page of redemptions, oldest first. Customer redemptions by default;
     * `type: manual_deduction` or `type: all` for the rest.
     *
     * @param  array{customer_id?: string, customer_external_id?: string, customer_external_source?: string, type?: string, status?: string, source_reference?: string, has_coupon?: bool, since?: string|\DateTimeInterface, limit?: int, cursor?: string}  $params
     * @return Collection<Redemption>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/redemptions', $params);
    }

    /**
     * @param  array{customer_id?: string, customer_external_id?: string, customer_external_source?: string, type?: string, status?: string, source_reference?: string, has_coupon?: bool, since?: string|\DateTimeInterface, limit?: int}  $params
     * @return Generator<int, Redemption>
     */
    public function all(array $params = []): Generator
    {
        return $this->list($params + ['limit' => 100])->autoPagingIterator();
    }

    public function retrieve(string $id): Redemption
    {
        return $this->object(Redemption::class, $this->requestor->request('GET', '/redemptions/'.self::segment($id, 'redemption id')));
    }

    /**
     * Spend points, oldest unexpired buckets first.
     *
     * - `points` must be within the programme's minimum and maximum
     *   (`GET /program`), else `invalid_redeem_request`; too few points is
     *   `insufficient_points`.
     * - With `generate_coupon_code: true` the answer carries a `QYR-…`
     *   reference. Create the real coupon on your shop for `monetary_value`;
     *   if it goes unused, reverse() the redemption.
     * - Idempotent on `source_reference` (one per attempt): `wasNew()` is
     *   false on a replay.
     * - Suspended account: AccountStateException `redemption_unavailable`.
     *
     * @param  array{points: int, source_reference: string, customer_id?: string|null, customer_external_id?: string|null, customer_external_source?: string|null, customer_card_number?: string|null, customer_phone?: string|null, location_id?: string|null, currency?: string|null, generate_coupon_code?: bool|null, metadata?: array<string, mixed>|null}  $params
     */
    public function create(array $params): Redemption
    {
        self::requireKeys($params, 'redemptions->create()', 'points', 'source_reference');

        return $this->object(Redemption::class, $this->requestor->request('POST', '/redemptions', body: $params));
    }

    /**
     * Undo a redemption (coupon removed, order refunded, cart abandoned). The
     * points go back to the exact buckets they came from, keeping their
     * expiry. Idempotent: `wasNewlyReversed()` is false on a repeat. Works
     * while the account is suspended.
     *
     * @param  array{reason?: string|null, triggered_by?: array<string, mixed>|null}  $params
     */
    public function reverse(string $id, array $params = []): Redemption
    {
        return $this->object(Redemption::class, $this->requestor->request('POST', '/redemptions/'.self::segment($id, 'redemption id').'/reverse', body: $params));
    }

    /**
     * Shallow-merge keys into the redemption's `metadata`, e.g. the order it
     * was used on. Idempotent; allowed on reversed redemptions.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function mergeMetadata(string $id, array $metadata): Redemption
    {
        return $this->object(Redemption::class, $this->requestor->request(
            'POST',
            '/redemptions/'.self::segment($id, 'redemption id').'/metadata',
            body: ['metadata' => $metadata],
        ));
    }

    /**
     * The redemption made with this source_reference (any type), or null.
     */
    public function findBySourceReference(string $sourceReference): ?Redemption
    {
        $first = $this->list(['source_reference' => $sourceReference, 'type' => 'all', 'limit' => 1])->first();

        return $first instanceof Redemption ? $first : null;
    }
}
