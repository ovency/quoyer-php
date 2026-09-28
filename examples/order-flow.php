<?php

/**
 * An online order's whole life: enrol, earn, redeem at the next checkout,
 * then a refund. Run it against a TEST merchant only:
 *
 *     QUOYER_API_KEY=quoy_sandbox_… QUOYER_BASE_URL=https://your-staging-install php examples/order-flow.php
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Quoyer\Enums\RuleType;
use Quoyer\ErrorCode;
use Quoyer\Exceptions\InvalidRequestException;
use Quoyer\Exceptions\NotFoundException;
use Quoyer\QuoyerClient;

$quoyer = new QuoyerClient([
    'api_key' => (string) getenv('QUOYER_API_KEY'),
    'base_url' => getenv('QUOYER_BASE_URL') ?: 'https://quoyer.com',
]);

$shopCustomerId = 'example-'.bin2hex(random_bytes(3));
$source = 'example-shop';

// 1. The customer registers.
$customer = $quoyer->customers->upsert([
    'external_id' => $shopCustomerId,
    'external_source' => $source,
    'email' => "{$shopCustomerId}@example.com",
    'first_name' => 'Example',
]);
echo "Enrolled {$customer->id}".($customer->wasCreated() ? ' (new)' : '').PHP_EOL;

// 2. They pay for an order. The reference makes this safe to send twice.
$credit = [
    'customer_external_id' => $shopCustomerId,
    'customer_external_source' => $source,
    'rule_type' => RuleType::Purchase,
    'source_reference' => "{$source}_order_1001_{$shopCustomerId}",
    'metadata' => ['amount' => 120.00, 'currency' => 'EUR'],
];

try {
    $result = $quoyer->points->credit($credit);
    echo "Earned {$result->pointsAwarded()} points".($result->note ? " ({$result->note})" : '').PHP_EOL;

    $again = $quoyer->points->credit($credit);
    echo 'Sent again: '.($again->wasNew() ? 'awarded twice?!' : 'recognised as the same order').PHP_EOL;
} catch (InvalidRequestException $e) {
    if ($e->getErrorCode() !== ErrorCode::NO_ACTIVE_RULE) {
        throw $e;
    }
    echo "This merchant has no purchase rule yet; nothing to earn.\n";
}

// 3. At the next checkout they spend what the programme allows.
$program = $quoyer->program->retrieve();
$balance = $quoyer->customers->findByExternalId($shopCustomerId, $source)?->points() ?? 0;
$spend = min($balance, $program->maximum_redemption_points ?? $balance);

if ($spend >= ($program->minimum_redemption_points ?? 1)) {
    $redemption = $quoyer->redemptions->create([
        'customer_external_id' => $shopCustomerId,
        'customer_external_source' => $source,
        'points' => $spend,
        'generate_coupon_code' => true,
        'source_reference' => "{$source}_cart_77_1_{$shopCustomerId}",
    ]);
    echo "Redeemed {$spend} points as {$redemption->coupon_code}, worth {$redemption->monetary_value} {$redemption->currency}".PHP_EOL;

    // The shopper removed the discount: the points go back.
    $quoyer->redemptions->reverse($redemption->id, ['reason' => 'coupon removed']);
    echo "Redemption reversed\n";
}

// 4. The first order is refunded.
try {
    $reversal = $quoyer->points->reverseCredit([
        'customer_external_id' => $shopCustomerId,
        'customer_external_source' => $source,
        'source_reference' => $credit['source_reference'],
        'reason' => 'refund',
    ]);
    echo "Refund took back {$reversal->pointsReversed()} points\n";
} catch (NotFoundException) {
    echo "Nothing had been credited for that order.\n";
}

echo 'Balance now: '.$quoyer->customers->retrieve($customer->id)->points().PHP_EOL;

// Clean up the test customer.
$quoyer->customers->delete($customer->id);
