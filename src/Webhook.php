<?php

declare(strict_types=1);

namespace Quoyer;

use JsonException;
use Quoyer\Exceptions\SignatureVerificationException;
use Quoyer\Resources\Event;

/**
 * Verifies and reads webhook deliveries.
 *
 * Quoyer signs every delivery:
 *
 *     X-Quoyer-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256(secret, "{t}.{raw body}")>
 *
 * Verify against the RAW request body, before any JSON parsing:
 *
 *     $event = Webhook::constructEvent(
 *         file_get_contents('php://input'),
 *         $_SERVER['HTTP_X_QUOYER_SIGNATURE'] ?? '',
 *         getenv('QUOYER_WEBHOOK_SECRET'),
 *     );
 */
final class Webhook
{
    /** Seconds a signature's timestamp may differ from this server's clock. */
    public const DEFAULT_TOLERANCE = 300;

    public const SIGNATURE_HEADER = 'X-Quoyer-Signature';

    public const EVENT_ID_HEADER = 'X-Quoyer-Event-Id';

    /**
     * Verify a delivery and return its event.
     *
     * @param  int|null  $tolerance  Seconds; null skips the timestamp check (tests only).
     *
     * @throws SignatureVerificationException
     */
    public static function constructEvent(
        string $payload,
        string $signatureHeader,
        string $secret,
        ?int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): Event {
        self::verifySignature($payload, $signatureHeader, $secret, $tolerance, $now);

        try {
            $data = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new SignatureVerificationException('The webhook body is not valid JSON.', 0, $e);
        }

        if (! is_array($data) || array_is_list($data)) {
            throw new SignatureVerificationException('The webhook body is not a JSON object.');
        }

        return Event::constructFrom($data);
    }

    /**
     * @param  int|null  $tolerance  Seconds; null skips the timestamp check (tests only).
     *
     * @throws SignatureVerificationException
     */
    public static function verifySignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        ?int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): void {
        if ($secret === '') {
            throw new SignatureVerificationException('The webhook secret is empty. Copy it from the endpoint in the Quoyer dashboard.');
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            [$key, $value] = array_pad(explode('=', $segment, 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';

        if ($timestamp === '' || $signature === '' || ! ctype_digit($timestamp)) {
            throw new SignatureVerificationException(sprintf('The %s header is missing or malformed.', self::SIGNATURE_HEADER));
        }

        if ($tolerance !== null && abs(($now ?? time()) - (int) $timestamp) > $tolerance) {
            throw new SignatureVerificationException('The webhook timestamp is outside the tolerance: a replay, or a wrong clock.');
        }

        if (! hash_equals(self::computeSignature($payload, $secret, (int) $timestamp), $signature)) {
            throw new SignatureVerificationException('The webhook signature does not match. Check the endpoint secret.');
        }
    }

    /**
     * The same check as verifySignature(), as a boolean.
     */
    public static function isValidSignature(
        string $payload,
        string $signatureHeader,
        string $secret,
        ?int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): bool {
        try {
            self::verifySignature($payload, $signatureHeader, $secret, $tolerance, $now);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }

    /**
     * The `v1` value: hex HMAC-SHA256 of `"{timestamp}.{payload}"`.
     */
    public static function computeSignature(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * A signature header as Quoyer would send it. For your own tests.
     */
    public static function generateSignatureHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't='.$timestamp.',v1='.self::computeSignature($payload, $secret, $timestamp);
    }
}
