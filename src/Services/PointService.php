<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Quoyer\Enums\RuleType;
use Quoyer\Resources\PointCreditResult;
use Quoyer\Resources\PointCreditReversalResult;

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
     * Take back EVERY bucket a credit produced (a refund, a cancellation).
     * Identify the customer as for credit(); `rule_type` defaults to
     * `purchase`.
     *
     * An already-reversed credit is a success: `wasNewlyReversed()` is false
     * and `points_reversed` is 0. Nothing credited for this reference throws
     * NotFoundException `bucket_not_found`, which you can treat as done.
     *
     * @param  array{source_reference: string, customer_id?: string|null, customer_external_id?: string|null, customer_external_source?: string|null, rule_type?: RuleType|string|null, reason?: string|null, triggered_by?: array<string, mixed>|null}  $params
     */
    public function reverseCredit(array $params): PointCreditReversalResult
    {
        self::requireKeys($params, 'points->reverseCredit()', 'source_reference');

        return $this->object(
            PointCreditReversalResult::class,
            $this->requestor->request('POST', '/points/credit/reverse', body: $params, acceptStatus: [409]),
        );
    }
}
