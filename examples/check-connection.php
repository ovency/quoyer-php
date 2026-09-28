<?php

/**
 * Checks a key against a Quoyer install and prints what the merchant's plan allows.
 *
 *     QUOYER_API_KEY=quoy_sandbox_… QUOYER_BASE_URL=https://your-staging-install php examples/check-connection.php
 */

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Quoyer\Exceptions\QuoyerException;
use Quoyer\QuoyerClient;

$quoyer = new QuoyerClient([
    'api_key' => (string) getenv('QUOYER_API_KEY'),
    'base_url' => getenv('QUOYER_BASE_URL') ?: 'https://quoyer.com',
]);

try {
    $me = $quoyer->me();
    $program = $quoyer->program->retrieve();
} catch (QuoyerException $e) {
    fwrite(STDERR, 'Failed: '.$e->getMessage().PHP_EOL);
    exit(1);
}

printf("Connected to %s (%s), account %s\n", $me->tenant->name, $me->tenant->id, $me->accountState());
printf("Programme: %s, %s, default currency %s\n", $program->name, $program->is_active ? 'active' : 'PAUSED', $program->default_currency ?? 'none');
printf("Members: up to %s active\n", $me->limit('members_active') ?? 'unlimited');
printf("VIP tiers: %s, referrals: %s\n", $me->hasFeature('vip_tiers') ? 'yes' : 'no', $me->hasFeature('referrals') ? 'yes' : 'no');
printf("Rate limit: %s reads a minute\n", $me->getLastResponse()?->rateLimit()?->limit ?? 'unknown');
