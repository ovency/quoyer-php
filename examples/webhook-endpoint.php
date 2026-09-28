<?php

/**
 * A plain-PHP webhook endpoint. Point the merchant's webhook URL at it and
 * set QUOYER_WEBHOOK_SECRET to the endpoint's secret.
 *
 * In Laravel, use the `quoyer.webhook` middleware instead (docs/laravel.md).
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Quoyer\Enums\EventType;
use Quoyer\Exceptions\SignatureVerificationException;
use Quoyer\Webhook;

try {
    $event = Webhook::constructEvent(
        (string) file_get_contents('php://input'),   // the raw body: verify before parsing
        $_SERVER['HTTP_X_QUOYER_SIGNATURE'] ?? '',
        (string) getenv('QUOYER_WEBHOOK_SECRET'),
    );
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    echo $e->getMessage();
    exit;
}

// Retries and replays carry the same id: process each event once. Use a
// unique key in your database here; a file keeps the example self-contained
// ('x' creates it atomically and fails if it already exists).
$marker = sys_get_temp_dir().'/quoyer-event-'.preg_replace('/[^A-Za-z0-9_]/', '', (string) $event->id);
$handle = @fopen($marker, 'x');
if ($handle === false) {
    http_response_code(200);   // seen before
    exit;
}
fclose($handle);

switch ($event->typeEnum()) {
    case EventType::RedemptionCreated:
        $redemption = $event->data->redemption;
        error_log("Redemption {$redemption->id}: {$redemption->points_redeemed} points, coupon {$redemption->coupon_code}");
        break;

    case EventType::PointsExpired:
        error_log("{$event->data->points_expired} points expired for {$event->data->customer->email}; balance now {$event->data->balance_after}");
        break;

    case EventType::CustomerTierChanged:
        error_log("{$event->data->customer->id} moved {$event->data->direction} to ".($event->data->to?->name ?? 'no tier'));
        break;

    default:
        // A type this code doesn't handle, or doesn't know yet: acknowledge it.
        break;
}

http_response_code(200);
