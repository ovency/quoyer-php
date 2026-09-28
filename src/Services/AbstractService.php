<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Quoyer\Collection;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\Exceptions\UnexpectedResponseException;
use Quoyer\Http\ApiResponse;
use Quoyer\Http\Requestor;
use Quoyer\QuoyerObject;

/**
 * @internal
 */
abstract class AbstractService
{
    public function __construct(protected readonly Requestor $requestor) {}

    /**
     * @template T of QuoyerObject
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function object(string $class, ApiResponse $response): QuoyerObject
    {
        if ($response->json === null || array_is_list($response->json) && $response->json !== []) {
            throw new UnexpectedResponseException(
                sprintf('Expected a JSON object from Quoyer, got HTTP %d: %s', $response->statusCode, substr(trim($response->body), 0, 200)),
                $response,
            );
        }

        return $class::constructFrom($response->json, $response);
    }

    /**
     * GET a list, wired for nextPage() and autoPagingIterator().
     *
     * @template T of Collection
     *
     * @param  array<string, mixed>  $params
     * @param  class-string<T>  $class
     * @return T
     */
    protected function listing(string $path, array $params = [], string $class = Collection::class): Collection
    {
        $response = $this->requestor->request('GET', $path, $params);

        return $this->object($class, $response)->withPageFetcher(
            fn (string $cursor): Collection => $this->listing($path, array_merge($params, ['cursor' => $cursor]), $class),
        );
    }

    /**
     * An id for a URL path: never empty, always encoded.
     */
    protected static function segment(string $id, string $what = 'id'): string
    {
        $id = trim($id);
        if ($id === '') {
            throw new InvalidArgumentException(sprintf('The %s must not be empty.', $what));
        }

        return rawurlencode($id);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected static function requireKeys(array $params, string $operation, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (! isset($params[$key]) || $params[$key] === '') {
                throw new InvalidArgumentException(sprintf('%s needs "%s".', $operation, $key));
            }
        }
    }
}
