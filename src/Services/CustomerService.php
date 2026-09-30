<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Generator;
use Quoyer\Collection;
use Quoyer\Enums\BucketStatus;
use Quoyer\Enums\RuleType;
use Quoyer\Resources\Customer;
use Quoyer\Resources\PointBucket;
use Quoyer\Resources\PointTransaction;
use Quoyer\Resources\Referral;

/**
 * Customers (loyalty members): `$quoyer->customers`.
 */
final class CustomerService extends AbstractService
{
    /**
     * A page of customers, oldest first. Filter by `email`, `phone`,
     * `card_number`, or `external_id` + `external_source`.
     *
     * @param  array{email?: string, phone?: string, card_number?: string, external_id?: string, external_source?: string, limit?: int, cursor?: string}  $params
     * @return Collection<Customer>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/customers', $params);
    }

    /**
     * Every customer matching $params, across all pages, fetched as you go.
     *
     * @param  array{email?: string, phone?: string, card_number?: string, external_id?: string, external_source?: string, limit?: int}  $params
     * @return Generator<int, Customer>
     */
    public function all(array $params = []): Generator
    {
        return $this->list($params + ['limit' => 100])->autoPagingIterator();
    }

    /**
     * Create or update a customer. Send it before every credit.
     *
     * Matching, in order: `(external_id, external_source)`, then `email`,
     * then `phone`; otherwise a new customer (it needs an email or a phone).
     * Omitted or null fields are left unchanged. `$customer->wasCreated()`
     * tells a new customer from an update.
     *
     * Throws InvalidRequestException `email_conflict`, `phone_conflict` or
     * `member_limit_reached` (the plan's cap refused a NEW member; existing
     * members are unaffected).
     *
     * `award_welcome: true` (v1.14) pays the merchant's sign-up rule: send it
     * when the shopper registers, not for a guest. Once per customer, whichever
     * path paid it first; the returned balance includes it.
     *
     * A merged customer's `cus_` id or external id reaches the customer it was
     * merged into (v1.14): compare the returned id with the one you hold.
     *
     * @param  array{external_id?: string|null, external_source?: string|null, email?: string|null, phone?: string|null, first_name?: string|null, last_name?: string|null, birthday?: string|null, referral_code?: string|null, locale?: string|null, email_opt_out?: bool|null, award_welcome?: bool|null}  $params
     */
    public function upsert(array $params): Customer
    {
        // Retrying an upsert is safe (it matches the same customer), except
        // that a referral code sent again for an existing customer is recorded
        // as a rejected referral. With a code, one attempt only.
        $retryable = ! isset($params['referral_code']);

        return $this->object(Customer::class, $this->requestor->request('POST', '/customers', body: $params, retryable: $retryable));
    }

    /**
     * A customer by `cus_` id, with `lifetime` totals.
     */
    public function retrieve(string $id): Customer
    {
        return $this->object(Customer::class, $this->requestor->request('GET', '/customers/'.self::segment($id, 'customer id')));
    }

    /**
     * Update fields of a customer. Null or omitted fields are left as they
     * are: a field cannot be cleared.
     *
     * @param  array{email?: string|null, first_name?: string|null, last_name?: string|null, external_id?: string|null, external_source?: string|null, birthday?: string|null}  $params
     */
    public function update(string $id, array $params): Customer
    {
        return $this->object(Customer::class, $this->requestor->request('PATCH', '/customers/'.self::segment($id, 'customer id'), body: $params));
    }

    /**
     * Permanently erase a customer: buckets, redemptions, wallet and ledger.
     * A `customer.deleted` webhook is sent. Refused while the account is
     * suspended. Never retried automatically.
     */
    public function delete(string $id): void
    {
        $this->requestor->request('DELETE', '/customers/'.self::segment($id, 'customer id'), retryable: false);
    }

    /**
     * The customer with `balance` and `lifetime` (the same body as retrieve()).
     */
    public function balance(string $id): Customer
    {
        return $this->object(Customer::class, $this->requestor->request('GET', '/customers/'.self::segment($id, 'customer id').'/balance'));
    }

    /**
     * The customer's wallet ledger, oldest first: earns, spends, refunds,
     * deductions, expiries and clawbacks.
     *
     * @param  array{limit?: int, cursor?: string}  $params
     * @return Collection<PointTransaction>
     */
    public function transactions(string $id, array $params = []): Collection
    {
        return $this->listing('/customers/'.self::segment($id, 'customer id').'/transactions', $params);
    }

    /**
     * The customer's point buckets, e.g. for "your points expire on …".
     *
     * @param  array{status?: BucketStatus|string, earning_rule_id?: string, earning_rule_type?: RuleType|string, expires_before?: string|\DateTimeInterface, expires_after?: string|\DateTimeInterface, issued_after?: string|\DateTimeInterface, limit?: int, cursor?: string}  $params
     * @return Collection<PointBucket>
     */
    public function buckets(string $id, array $params = []): Collection
    {
        return $this->listing('/customers/'.self::segment($id, 'customer id').'/buckets', $params);
    }

    /**
     * The people this member referred, rejected attempts included. Throws
     * InvalidRequestException `feature_not_in_plan` without referrals.
     *
     * @param  array{limit?: int, cursor?: string}  $params
     * @return Collection<Referral>
     */
    public function referrals(string $id, array $params = []): Collection
    {
        return $this->listing('/customers/'.self::segment($id, 'customer id').'/referrals', $params);
    }

    /**
     * The customer with your id on your platform, or null.
     */
    public function findByExternalId(string $externalId, string $externalSource): ?Customer
    {
        return $this->findOne(['external_id' => $externalId, 'external_source' => $externalSource]);
    }

    /**
     * The customer with this email (case-insensitive), or null.
     */
    public function findByEmail(string $email): ?Customer
    {
        return $this->findOne(['email' => $email]);
    }

    /**
     * The customer with this phone, in any typed form, or null.
     */
    public function findByPhone(string $phone): ?Customer
    {
        return $this->findOne(['phone' => $phone]);
    }

    /**
     * The customer with this Quoyer card number (spaces ignored), or null.
     */
    public function findByCardNumber(string $cardNumber): ?Customer
    {
        return $this->findOne(['card_number' => $cardNumber]);
    }

    /**
     * @param  array<string, string>  $filter
     */
    private function findOne(array $filter): ?Customer
    {
        $first = $this->listing('/customers', $filter + ['limit' => 1])->first();

        return $first instanceof Customer ? $first : null;
    }
}
