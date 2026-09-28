<?php

declare(strict_types=1);

namespace Quoyer;

use ArrayIterator;
use Closure;
use Generator;
use IteratorAggregate;

/**
 * One page of a list: `{object: "list", data: [...], has_more, next_cursor}`.
 * Items come oldest first.
 *
 * Iterate the page, or every page:
 *
 *     foreach ($quoyer->customers->list(['limit' => 100]) as $customer) { … }   // this page
 *     foreach ($quoyer->customers->all() as $customer) { … }                     // every page, fetched as you go
 *
 * @template TItem
 *
 * @property-read string $object `list`
 * @property-read list<TItem> $data
 * @property-read bool $has_more
 * @property-read string|null $next_cursor Pass back as `cursor` for the next page; null on the last page.
 *
 * @implements IteratorAggregate<int, TItem>
 */
class Collection extends QuoyerObject implements IteratorAggregate
{
    /** @var (Closure(string): static)|null */
    private ?Closure $pageFetcher = null;

    /**
     * @param  Closure(string): static  $fetcher  Fetches the page after a cursor.
     *
     * @internal
     */
    public function withPageFetcher(Closure $fetcher): static
    {
        $this->pageFetcher = $fetcher;

        return $this;
    }

    /**
     * @return list<TItem>
     */
    public function data(): array
    {
        $data = $this->values['data'] ?? [];

        /** @var list<TItem> */
        return is_array($data) ? array_values($data) : [];
    }

    public function hasMore(): bool
    {
        return $this->bool('has_more');
    }

    public function nextCursor(): ?string
    {
        return $this->stringOrNull('next_cursor');
    }

    public function isEmpty(): bool
    {
        return $this->data() === [];
    }

    /**
     * @return TItem|null
     */
    public function first(): mixed
    {
        return $this->data()[0] ?? null;
    }

    /**
     * The number of items on THIS page.
     */
    public function count(): int
    {
        return count($this->data());
    }

    /**
     * @return ArrayIterator<int, TItem>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->data());
    }

    /**
     * The next page, or null on the last one.
     */
    public function nextPage(): ?static
    {
        $cursor = $this->nextCursor();

        if (! $this->hasMore() || $cursor === null || $this->pageFetcher === null) {
            return null;
        }

        return ($this->pageFetcher)($cursor);
    }

    /**
     * Every item on this page and every page after it, fetching each page
     * only when the previous one is used up.
     *
     * @return Generator<int, TItem>
     */
    public function autoPagingIterator(): Generator
    {
        $page = $this;

        while ($page !== null) {
            foreach ($page->data() as $item) {
                yield $item;
            }

            $page = $page->nextPage();
        }
    }
}
