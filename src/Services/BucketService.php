<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Generator;
use Quoyer\Collection;
use Quoyer\Enums\BucketStatus;
use Quoyer\Enums\RuleType;
use Quoyer\Resources\PointBucket;

/**
 * Point buckets, read-only: `$quoyer->buckets`. For one customer's, see
 * `$quoyer->customers->buckets($id)`.
 */
final class BucketService extends AbstractService
{
    /**
     * @param  array{customer_id?: string, customer_external_id?: string, customer_external_source?: string, earning_rule_id?: string, earning_rule_type?: RuleType|string, status?: BucketStatus|string, expires_before?: string|\DateTimeInterface, expires_after?: string|\DateTimeInterface, issued_after?: string|\DateTimeInterface, limit?: int, cursor?: string}  $params
     * @return Collection<PointBucket>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/buckets', $params);
    }

    /**
     * @param  array{customer_id?: string, customer_external_id?: string, customer_external_source?: string, earning_rule_id?: string, earning_rule_type?: RuleType|string, status?: BucketStatus|string, expires_before?: string|\DateTimeInterface, expires_after?: string|\DateTimeInterface, issued_after?: string|\DateTimeInterface, limit?: int}  $params
     * @return Generator<int, PointBucket>
     */
    public function all(array $params = []): Generator
    {
        return $this->list($params + ['limit' => 100])->autoPagingIterator();
    }

    public function retrieve(string $id): PointBucket
    {
        return $this->object(PointBucket::class, $this->requestor->request('GET', '/buckets/'.self::segment($id, 'bucket id')));
    }
}
