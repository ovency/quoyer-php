<?php

declare(strict_types=1);

namespace Quoyer\Enums;

/**
 * Webhook event types (contract v1.9). New types are added without notice:
 * use Event::typeEnum(), which is null for a type this SDK does not know,
 * and answer 2xx to anything you ignore.
 */
enum EventType: string
{
    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeleted = 'customer.deleted';
    case CustomerTierChanged = 'customer.tier_changed';
    case ReferralRewarded = 'referral.rewarded';
    case PointCreditCreated = 'point_credit.created';
    case PointCreditReversed = 'point_credit.reversed';
    case RedemptionCreated = 'redemption.created';
    case RedemptionReversed = 'redemption.reversed';
    case PointsExpired = 'points.expired';
}
