<?php

declare(strict_types=1);

namespace Quoyer;

use ArrayAccess;
use Countable;
use JsonSerializable;
use LogicException;
use Quoyer\Http\ApiResponse;
use Quoyer\Resources\CatalogueItem;
use Quoyer\Resources\CatalogueSyncResult;
use Quoyer\Resources\Currency;
use Quoyer\Resources\Customer;
use Quoyer\Resources\EarningRule;
use Quoyer\Resources\Event;
use Quoyer\Resources\Me;
use Quoyer\Resources\PointBucket;
use Quoyer\Resources\PointCreditResult;
use Quoyer\Resources\PointCreditReversalResult;
use Quoyer\Resources\PointTransaction;
use Quoyer\Resources\Program;
use Quoyer\Resources\Redemption;
use Quoyer\Resources\Referral;
use Quoyer\Resources\Reward;
use Quoyer\Resources\Tier;

/**
 * A read-only view of one JSON object from the API.
 *
 * Read fields as properties or array keys; both return null for a field that
 * is absent:
 *
 *     $customer->email;
 *     $customer['email'];
 *     $customer->balance->points;
 *
 * Nested objects become QuoyerObjects too, typed by their `object` field
 * (`customer` → Customer, `point_bucket` → PointBucket, …). Fields the SDK
 * does not know yet are kept and readable: Quoyer adds fields without a
 * version bump, and this SDK never drops them.
 *
 * @implements ArrayAccess<string, mixed>
 */
class QuoyerObject implements ArrayAccess, Countable, JsonSerializable
{
    /**
     * The `object` value each class stands for.
     *
     * @var array<string, class-string<QuoyerObject>>
     */
    private const TYPES = [
        'catalogue_item' => CatalogueItem::class,
        'catalogue_sync_result' => CatalogueSyncResult::class,
        'currency' => Currency::class,
        'customer' => Customer::class,
        'earning_rule' => EarningRule::class,
        'event' => Event::class,
        'list' => Collection::class,
        'me' => Me::class,
        'point_bucket' => PointBucket::class,
        'point_credit_result' => PointCreditResult::class,
        'point_credit_reversal_result' => PointCreditReversalResult::class,
        'point_transaction' => PointTransaction::class,
        'program' => Program::class,
        'redemption' => Redemption::class,
        'referral' => Referral::class,
        'reward' => Reward::class,
        'tier' => Tier::class,
    ];

    /** @var array<string, mixed> Converted values. */
    protected array $values = [];

    /** @var array<string, mixed> The JSON as received. */
    protected array $raw = [];

    protected ?ApiResponse $lastResponse = null;

    final public function __construct() {}

    /**
     * @param  array<array-key, mixed>  $values  A decoded JSON object.
     */
    public static function constructFrom(array $values, ?ApiResponse $response = null): static
    {
        $object = new static;

        foreach ($values as $key => $value) {
            $object->raw[(string) $key] = $value;
            $object->values[(string) $key] = self::convert($value);
        }

        $object->lastResponse = $response;

        return $object;
    }

    /**
     * Converts decoded JSON: objects to QuoyerObjects, lists element by element.
     */
    public static function convert(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::convert(...), $value);
        }

        $type = $value['object'] ?? null;
        $class = is_string($type) && isset(self::TYPES[$type]) ? self::TYPES[$type] : self::class;

        return $class::constructFrom($value);
    }

    /**
     * The response this object came from. Null for nested objects and for
     * objects built by hand (e.g. a webhook event).
     */
    public function getLastResponse(): ?ApiResponse
    {
        return $this->lastResponse;
    }

    /**
     * Whether the field is present, even when its value is null.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->values);
    }

    /**
     * The object exactly as the API sent it, nested objects included.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    public function count(): int
    {
        return count($this->values);
    }

    public function __get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function __set(string $name, mixed $value): void
    {
        throw new LogicException(sprintf('Quoyer objects are read-only; cannot set "%s". Send changes through the API.', $name));
    }

    public function __unset(string $name): void
    {
        throw new LogicException(sprintf('Quoyer objects are read-only; cannot unset "%s".', $name));
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->values[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->values[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Quoyer objects are read-only. Send changes through the API.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('Quoyer objects are read-only.');
    }

    /**
     * An integer field, or $default when it is absent or not an integer.
     */
    protected function int(string $key, int $default = 0): int
    {
        $value = $this->values[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    protected function bool(string $key): bool
    {
        return ($this->values[$key] ?? false) === true;
    }

    protected function stringOrNull(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
