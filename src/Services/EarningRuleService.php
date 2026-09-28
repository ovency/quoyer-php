<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Generator;
use Quoyer\Collection;
use Quoyer\Enums\RuleType;
use Quoyer\Resources\EarningRule;

/**
 * How points are awarded: `$quoyer->earningRules`. Admin writes are refused
 * while the merchant's account is suspended (`account_read_only`).
 */
final class EarningRuleService extends AbstractService
{
    /**
     * Active rules only, unless `include_inactive` is true.
     *
     * @param  array{type?: RuleType|string, include_inactive?: bool, is_active?: bool, currently_active?: bool, limit?: int, cursor?: string}  $params
     * @return Collection<EarningRule>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/earning-rules', $params);
    }

    /**
     * @param  array{type?: RuleType|string, include_inactive?: bool, is_active?: bool, currently_active?: bool, limit?: int}  $params
     * @return Generator<int, EarningRule>
     */
    public function all(array $params = []): Generator
    {
        return $this->list($params + ['limit' => 100])->autoPagingIterator();
    }

    public function retrieve(string $id): EarningRule
    {
        return $this->object(EarningRule::class, $this->requestor->request('GET', '/earning-rules/'.self::segment($id, 'rule id')));
    }

    /**
     * `name` and `type` are required; `fixed_points` too for signup, birthday
     * and newsletter_signup. Never retried automatically: a lost answer
     * followed by a retry would create the rule twice.
     *
     * @param  array{name: string, type: RuleType|string, description?: string|null, is_active?: bool, fixed_points?: int|null, award_days_before?: int|null, friend_points?: int|null, monthly_cap?: int|null, minimum_review_length?: int|null, minimum_amount?: int|float|string|null, expiry_days_override?: int|null, active_from?: string|\DateTimeInterface|null, active_until?: string|\DateTimeInterface|null, stacks?: bool, priority?: int, points_multiplier?: int|null, target_product_ids?: list<string|int>|null, target_category_ids?: list<string|int>|null, target_brand_ids?: list<string|int>|null}  $params
     */
    public function create(array $params): EarningRule
    {
        self::requireKeys($params, 'earningRules->create()', 'name', 'type');

        return $this->object(EarningRule::class, $this->requestor->request('POST', '/earning-rules', body: $params, retryable: false));
    }

    /**
     * Only the fields sent change; `type` cannot. There is no delete: send
     * `is_active: false`.
     *
     * @param  array{name?: string, description?: string|null, is_active?: bool, fixed_points?: int|null, award_days_before?: int|null, friend_points?: int|null, monthly_cap?: int|null, minimum_review_length?: int|null, minimum_amount?: int|float|string|null, expiry_days_override?: int|null, active_from?: string|\DateTimeInterface|null, active_until?: string|\DateTimeInterface|null, stacks?: bool, priority?: int, points_multiplier?: int|null, target_product_ids?: list<string|int>|null, target_category_ids?: list<string|int>|null, target_brand_ids?: list<string|int>|null}  $params
     */
    public function update(string $id, array $params): EarningRule
    {
        return $this->object(EarningRule::class, $this->requestor->request('PATCH', '/earning-rules/'.self::segment($id, 'rule id'), body: $params));
    }
}
