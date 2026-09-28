<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\QuoyerObject;

/**
 * Who the API key belongs to and what the merchant's plan allows.
 * Call it first: it confirms the key works.
 *
 * Read capabilities from `entitlements` (or hasFeature() / limit()), never
 * from a plan name. `limits`, `rate_limits` and `tenant.tier` are deprecated.
 *
 * @property-read string $object `me`
 * @property-read QuoyerObject $tenant `id`, `name`, `subdomain`, `dashboard_url`, `state`, `plan`, `is_founder`, `trial_ends_at`.
 * @property-read QuoyerObject $platform `name`, `store_name`, `store_url`, `store_url_etld1`.
 * @property-read QuoyerObject $api_key `id`, `name`, `masked`, `last_used_at`, `created_at`.
 * @property-read QuoyerObject $entitlements `features` (name → bool) and `limits` (name → int, null = unlimited).
 * @property-read QuoyerObject $program `default_currency`, `active_currencies`, `earning_rules_count`.
 * @property-read QuoyerObject|null $store The storefront named by this client's store_url, as registered.
 * @property-read QuoyerObject $stores `count` and `limit` (null = unlimited).
 * @property-read QuoyerObject $brand `logo_url`, `email_logo_url`, `favicon_url`, `accent`, `powered_by_url`.
 * @property-read string $documentation_url
 * @property-read QuoyerObject $limits Deprecated: read entitlements.
 * @property-read QuoyerObject $rate_limits Deprecated: read entitlements.limits.api_per_min.
 */
final class Me extends QuoyerObject
{
    /**
     * Whether the plan includes a feature, e.g. `vip_tiers`, `referrals`,
     * `custom_domain`. An unknown or missing feature is off.
     */
    public function hasFeature(string $feature): bool
    {
        $features = $this->entitlements()?->features;

        return $features instanceof QuoyerObject && $features->{$feature} === true;
    }

    /**
     * A plan limit, e.g. `members_active`, `platforms`, `api_per_min`.
     * Null means UNLIMITED; a limit the API does not name is 0.
     */
    public function limit(string $limit): ?int
    {
        $limits = $this->entitlements()?->limits;

        if (! $limits instanceof QuoyerObject || ! $limits->has($limit)) {
            return 0;
        }

        $value = $limits->{$limit};

        return is_int($value) ? $value : null;
    }

    /**
     * `trial`, `active`, `past_due`, `suspended` or `cancelled`.
     */
    public function accountState(): ?string
    {
        $tenant = $this->values['tenant'] ?? null;

        return $tenant instanceof QuoyerObject && is_string($tenant->state) ? $tenant->state : null;
    }

    private function entitlements(): ?QuoyerObject
    {
        $entitlements = $this->values['entitlements'] ?? null;

        return $entitlements instanceof QuoyerObject ? $entitlements : null;
    }
}
