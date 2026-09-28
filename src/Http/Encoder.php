<?php

declare(strict_types=1);

namespace Quoyer\Http;

use BackedEnum;
use DateTimeInterface;
use JsonException;
use Quoyer\Exceptions\InvalidArgumentException;
use Quoyer\QuoyerObject;

/**
 * Turns SDK arguments into query strings and JSON bodies.
 *
 * Accepts enums (sent as their value), dates (sent as ISO 8601) and Quoyer
 * objects (sent as the array they came from) anywhere in the input.
 *
 * @internal
 */
final class Encoder
{
    /**
     * Nulls are dropped; booleans are sent as `true` / `false`.
     *
     * @param  array<string, mixed>  $params
     */
    public static function query(array $params): string
    {
        $clean = [];
        foreach ($params as $key => $value) {
            $value = self::normalise($value);
            if ($value === null) {
                continue;
            }
            $clean[$key] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }

        return http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * An empty body is sent as `{}`, never `[]`.
     *
     * @param  array<array-key, mixed>  $body
     */
    public static function body(array $body): string
    {
        $normalised = self::normalise($body);
        if ($normalised === []) {
            return '{}';
        }

        try {
            return json_encode(
                $normalised,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $e) {
            throw new InvalidArgumentException('The request body cannot be encoded as JSON: '.$e->getMessage(), 0, $e);
        }
    }

    public static function normalise(mixed $value): mixed
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof DateTimeInterface => $value->format(DATE_ATOM),
            $value instanceof QuoyerObject => $value->toArray(),
            is_array($value) => array_map(self::normalise(...), $value),
            default => $value,
        };
    }
}
