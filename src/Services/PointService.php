<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Quoyer\Enums\RuleType;
use Quoyer\Resources\PointCreditResult;
use Quoyer\Resources\PointCreditReversalResult;
use Quoyer\Resources\PointsPreview;

/**
 * Crediting points, and taking them back: `$quoyer->points`.
 *
 * Both calls are idempotent on `source_reference`: build ONE stable
 * reference per business event (e.g. `myshop_order_{order_id}`) and send it
 * every time. A repeat returns the original result, so a retry after a
 * timeout can never award twice.
 */
final class PointService extends AbstractService
{
    /**
     * Award points for an event: an order, a signup, a newsletter
     * subscription, a review, a manual grant.
     *
     * Name the customer with `customer_id`, or `customer_external_id` +
     * `customer_external_source`, or (in store) `customer_card_number` /
     * `customer_phone`. Name the rule with `rule_type` (usual) or `rule_id`.
     *
     * - `purchase` needs `metadata.amount` (the order total paid) and
     *   optionally `metadata.currency` and `metadata.line_items`.
     * - `manual_grant` needs an integer `metadata.amount`.
     * - Never credit `birthday` or `referral`: Quoyer does.
     *
     * Zero points (e.g. below the rule's minimum) is a success with a `note`.
     * `$result->wasNew()` is false on a replay.
     *
     * @param  array{source_reference: string, customer_id?: string|null, customer_external_id?: string|null, customer_external_source?: string|null, customer_card_number?: string|null, customer_phone?: string|null, location_id?: string|null, rule_type?: RuleType|string|null, rule_id?: string|null, metadata?: array<string, mixed>|null, occurred_at?: string|\DateTimeInterface|null}  $params
     */
    public function credit(array $params): PointCreditResult
    {
        self::requireKeys($params, 'points->credit()', 'source_reference');

        return $this->object(PointCreditResult::class, $this->requestor->request('POST', '/points/credit', body: $params));
    }

    /**
     * Pay the merchant's `custom` earning rule for an event it named (v1.14):
     * a class attended, a check-in, a survey answered. `event` is the rule's
     * event key; `source_reference` makes it idempotent, like credit().
     *
     * An event key no active custom rule has throws InvalidRequestException
     * `unknown_event`.
     *
     * @param  array{event: string, source_reference: string, metadata?: array<string, mixed>|null}  $params
     */
    public function awardEvent(string $customerId, array $params): PointCreditResult
    {
        self::requireKeys($params, 'points->awardEvent()', 'event', 'source_reference');

        return $this->object(PointCreditResult::class, $this->requestor->request('POST', '/customers/'.self::segment($customerId, 'customer id').'/events', body: $params));
    }

    /**
     * Take back EVERY bucket a credit produced (a refund, a cancellation).
     * Identify the customer as for credit(); `rule_type` defaults to
     * `purchase`.
     *
     * An already-reversed credit is a success: `wasNewlyReversed()` is false
     * and `points_reversed` is 0. Nothing credited for this reference throws
     * NotFoundException `bucket_not_found`, which you can treat as done.
     *
     * @param  array{source_reference: string, customer_id?: string|null, customer_external_id?: string|null, customer_external_source?: string|null, rule_type?: RuleType|string|null, amount?: float|int|null, points?: int|null, refund_reference?: string|null, reason?: string|null, triggered_by?: array<string, mixed>|null}  $params
     */
    public function reverseCredit(array $params): PointCreditReversalResult
    {
        self::requireKeys($params, 'points->reverseCredit()', 'source_reference');

        return $this->object(
            PointCreditReversalResult::class,
            $this->requestor->request('POST', '/points/credit/reverse', body: $params, acceptStatus: [409]),
        );
    }

    /**
     * What an order would earn, for "Earn 12 points" on a product page or in
     * the cart (v1.15). Runs the purchase rules exactly as credit() would and,
     * when a customer is named, adds their tier's bonus. Writes nothing and
     * needs no customer.
     *
     * Send the price times quantity as `amount` and a line item per product,
     * so targeted rules price correctly. Cache the answer per product, price,
     * currency and tier.
     *
     * @param  array{amount: float|int|string, currency?: string|null, line_items?: list<array<string, mixed>>, customer_id?: string|null, customer_external_id?: string|null, customer_external_source?: string|null, customer_card_number?: string|null, customer_phone?: string|null, location_id?: string|null}  $params
     */
    public function preview(array $params): PointsPreview
    {
        self::requireKeys($params, 'points->preview()', 'amount');

        return $this->object(PointsPreview::class, $this->requestor->request('POST', '/points/preview', body: $params));
    }
}
