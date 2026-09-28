<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Generator;
use Quoyer\Collection;
use Quoyer\Resources\Currency;

/**
 * Earning rates per currency, by ISO 4217 code: `$quoyer->currencies`.
 */
final class CurrencyService extends AbstractService
{
    /**
     * Active and inactive, unless `active` is given.
     *
     * @param  array{active?: bool, limit?: int, cursor?: string}  $params
     * @return Collection<Currency>
     */
    public function list(array $params = []): Collection
    {
        return $this->listing('/currencies', $params);
    }

    /**
     * @param  array{active?: bool, limit?: int}  $params
     * @return Generator<int, Currency>
     */
    public function all(array $params = []): Generator
    {
        return $this->list($params + ['limit' => 100])->autoPagingIterator();
    }

    public function retrieve(string $code): Currency
    {
        return $this->object(Currency::class, $this->requestor->request('GET', '/currencies/'.self::segment($code, 'currency code')));
    }

    /**
     * Add a currency. `point_value_minor_units` is derived and must not be
     * sent. An existing code throws InvalidRequestException
     * `currency_already_exists`: use update(). Never retried automatically.
     *
     * @param  array{currency_code: string, currency_units_per_point: int, is_active?: bool|null, notes?: string|null}  $params
     */
    public function create(array $params): Currency
    {
        self::requireKeys($params, 'currencies->create()', 'currency_code', 'currency_units_per_point');

        return $this->object(Currency::class, $this->requestor->request('POST', '/currencies', body: $params, retryable: false));
    }

    /**
     * To retire a currency, send `is_active: false`.
     *
     * @param  array{currency_units_per_point?: int, is_active?: bool, notes?: string|null}  $params
     */
    public function update(string $code, array $params): Currency
    {
        return $this->object(Currency::class, $this->requestor->request('PATCH', '/currencies/'.self::segment($code, 'currency code'), body: $params));
    }
}
