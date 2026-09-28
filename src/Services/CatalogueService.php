<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Generator;
use Quoyer\Collection;
use Quoyer\Enums\CatalogueKind;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Resources\CatalogueItem;
use Quoyer\Resources\CatalogueSyncResult;

/**
 * The merchant's products, categories and brands, so earning rules can be
 * set up by name: `$quoyer->catalogue`. A name lookup, never an authority:
 * points are computed from the line items an order carries.
 */
final class CatalogueService extends AbstractService
{
    /** The most items one request may carry. */
    public const MAX_BATCH = 1000;

    /**
     * @param  array{kind?: CatalogueKind|string, search?: string, limit?: int, cursor?: string}  $params
     * @return Collection<CatalogueItem>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/catalogue', $params);
    }

    /**
     * @param  array{kind?: CatalogueKind|string, search?: string, limit?: int}  $params
     * @return Generator<int, CatalogueItem>
     */
    public function all(array $params = []): Generator
    {
        return $this->list($params + ['limit' => 100])->autoPagingIterator();
    }

    /**
     * Upsert up to 1,000 items by `(kind, id)`. A malformed row is skipped
     * and counted, not fatal. For more items, use sync().
     *
     * @param  list<array{kind: CatalogueKind|string, id: string|int, name: string, parent_id?: string|null, url?: string|null, image_url?: string|null}>  $items
     */
    public function upsert(array $items): CatalogueSyncResult
    {
        $count = count($items);
        if ($count === 0 || $count > self::MAX_BATCH) {
            throw new InvalidArgumentException(sprintf('catalogue->upsert() takes 1 to %d items, got %d. Use catalogue->sync() for more.', self::MAX_BATCH, $count));
        }

        return $this->object(CatalogueSyncResult::class, $this->requestor->request('POST', '/catalogue', body: ['items' => array_values($items)]));
    }

    /**
     * Upsert any number of items, in batches of $batchSize, and add up the
     * counts. Send the whole catalogue: there is nothing to delete first.
     *
     * @param  iterable<array{kind: CatalogueKind|string, id: string|int, name: string, parent_id?: string|null, url?: string|null, image_url?: string|null}>  $items
     */
    public function sync(iterable $items, int $batchSize = self::MAX_BATCH): CatalogueSyncResult
    {
        if ($batchSize < 1 || $batchSize > self::MAX_BATCH) {
            throw new InvalidArgumentException(sprintf('The batch size must be between 1 and %d.', self::MAX_BATCH));
        }

        $totals = ['object' => 'catalogue_sync_result', 'received' => 0, 'upserted' => 0, 'skipped' => 0];
        $batch = [];

        foreach ($items as $item) {
            $batch[] = $item;
            if (count($batch) === $batchSize) {
                $this->addCounts($totals, $this->upsert($batch));
                $batch = [];
            }
        }

        if ($batch !== []) {
            $this->addCounts($totals, $this->upsert($batch));
        }

        return CatalogueSyncResult::constructFrom($totals);
    }

    /**
     * @param  array{object: string, received: int, upserted: int, skipped: int}  $totals
     */
    private function addCounts(array &$totals, CatalogueSyncResult $result): void
    {
        foreach (['received', 'upserted', 'skipped'] as $key) {
            $totals[$key] += is_int($result->{$key}) ? $result->{$key} : 0;
        }
    }
}
