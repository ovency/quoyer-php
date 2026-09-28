<?php

declare(strict_types=1);

namespace Quoyer\Resources;

use Quoyer\Enums\EventType;
use Quoyer\QuoyerObject;

/**
 * A webhook event, from Webhook::constructEvent().
 *
 * Deduplicate on `id`: it is the same on every retry and replay. Order is
 * not guaranteed; use `created_at` or read the resource back from the API.
 *
 * @property-read string $id `evt_…`, the same as `X-Quoyer-Event-Id`.
 * @property-read string $object `event`
 * @property-read string $type e.g. `redemption.created`. See EventType.
 * @property-read string $created_at
 * @property-read QuoyerObject $data The payload: `customer`, `redemption`, `bucket`, `referral`… by type.
 */
final class Event extends QuoyerObject
{
    /**
     * The type as an enum, or null for a type this SDK version does not know
     * (new types are added without notice: answer 2xx and ignore them).
     */
    public function typeEnum(): ?EventType
    {
        $type = $this->stringOrNull('type');

        return $type === null ? null : EventType::tryFrom($type);
    }

    public function isType(EventType|string $type): bool
    {
        $value = $type instanceof EventType ? $type->value : $type;

        return $this->stringOrNull('type') === $value;
    }
}
