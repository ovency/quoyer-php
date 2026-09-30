<?php

declare(strict_types=1);

namespace Quoyer\Enums;

/**
 * Earning rule types. Pass the enum or its string value.
 *
 * Credit only Purchase, Signup, NewsletterSignup, ManualGrant and Review
 * yourself. Birthday and Referral are awarded by Quoyer; crediting a
 * referral is refused.
 */
enum RuleType: string
{
    /** Points from `metadata.amount` at the currency's rate. */
    case Purchase = 'purchase';

    /** A one-off bonus of the rule's `fixed_points`. */
    case Signup = 'signup';

    /** Awarded by Quoyer each year from the customer's `birthday`. Never credit it. */
    case Birthday = 'birthday';

    /** `metadata.amount` points (an integer), for staff tools. */
    case ManualGrant = 'manual_grant';

    /** A one-off bonus of the rule's `fixed_points`. */
    case NewsletterSignup = 'newsletter_signup';

    /** Paid by Quoyer on a referred friend's first order. Never credit it. */
    case Referral = 'referral';

    /** `fixed_points` for an approved product review. */
    case Review = 'review';

    /** v1.14. Following on the rule's network, claimed by the customer in their account. Never credit it. */
    case SocialFollow = 'social_follow';

    /** v1.14. Sharing on the rule's network, claimed by the customer, at most once a day. Never credit it. */
    case SocialShare = 'social_share';

    /** v1.14. An event the merchant names (`event_key`): award it with `points->awardEvent()`. */
    case Custom = 'custom';
}
