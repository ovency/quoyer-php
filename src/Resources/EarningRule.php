<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * How points are awarded. A rule's `type` cannot change after creation, and
 * rules are never deleted: set `is_active` to false.
 *
 * @property-read string $id `rul_…`
 * @property-read string $object `earning_rule`
 * @property-read string $name
 * @property-read string $type See RuleType.
 * @property-read string|null $description
 * @property-read bool $is_active
 * @property-read bool $is_currently_active is_active AND now is inside active_from / active_until.
 * @property-read int|null $fixed_points The award for signup, birthday, newsletter and review rules.
 * @property-read int $award_days_before Birthday rules.
 * @property-read int|null $friend_points Referral rules: the friend's reward.
 * @property-read int|null $monthly_cap Referral rules. Null = 20, 0 = unlimited.
 * @property-read int|null $minimum_review_length Review rules.
 * @property-read string|null $minimum_amount Purchase rules, e.g. `"10.00"`.
 * @property-read int|null $expiry_days_override
 * @property-read string|null $active_from
 * @property-read string|null $active_until
 * @property-read QuoyerObject|array<mixed> $config Reserved; always empty.
 * @property-read bool $stacks False: a base rule. True: a bonus on top of the base.
 * @property-read int $priority Higher wins, between base rules only.
 * @property-read int|null $points_multiplier Hundredths: 200 pays double.
 * @property-read list<string>|null $target_product_ids
 * @property-read list<string>|null $target_category_ids
 * @property-read list<string>|null $target_brand_ids
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class EarningRule extends QuoyerObject {}
