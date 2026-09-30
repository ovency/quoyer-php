<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Quoyer\Collection;
use Quoyer\Resources\Redemption;
use Quoyer\Resources\Reward;

/**
 * The reward catalogue (API contract v1.11): `$quoyer->rewards`.
 */
final class RewardService extends AbstractService
{
    /**
     * Every reward on offer now, cheapest first, in one page. Name a customer
     * (`customer_id`, `customer_external_id` + `customer_external_source`,
     * `customer_card_number` or `customer_phone`) and each reward carries
     * `customer`: whether they can redeem it now (`canRedeemFor()`).
     *
     * @param  array{customer_id?: string, customer_external_id?: string, customer_external_source?: string, customer_card_number?: string, customer_phone?: string}  $params
     * @return Collection<Reward>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/rewards', $params);
    }

    public function retrieve(string $id): Reward
    {
        return $this->object(Reward::class, $this->requestor->request('GET', '/rewards/'.self::segment($id, 'reward id')));
    }

    /**
     * Redeem a reward for a customer. Answers the redemption: `reward` says
     * what to apply on the shop, `coupon_code` is always set. The programme's
     * minimum and maximum do not apply. Idempotent on `source_reference`
     * (`wasNew()` is false on a replay). Undo with `redemptions->reverse()`.
     *
     * Refusals: `reward_unavailable` (with `reason`), `reward_limit_reached`,
     * `insufficient_points`.
     *
     * @param  array{source_reference: string, customer_id?: string|null, customer_external_id?: string|null, customer_external_source?: string|null, customer_card_number?: string|null, customer_phone?: string|null, location_id?: string|null, currency?: string|null, metadata?: array<string, mixed>|null}  $params
     */
    public function redeem(string $id, array $params): Redemption
    {
        self::requireKeys($params, 'rewards->redeem()', 'source_reference');

        return $this->object(Redemption::class, $this->requestor->request('POST', '/rewards/'.self::segment($id, 'reward id').'/redeem', body: $params));
    }
}
