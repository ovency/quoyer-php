<?php

declare(strict_types=1);

namespace Quoyer\Services;

use Quoyer\Resources\Program;

/**
 * The programme's settings (one per merchant): `$quoyer->program`.
 */
final class ProgramService extends AbstractService
{
    public function retrieve(): Program
    {
        return $this->object(Program::class, $this->requestor->request('GET', '/program'));
    }

    /**
     * Only the fields sent change. `is_active: false` pauses earning and
     * redeeming. `redemption_return_bp` is set in the dashboard.
     *
     * @param  array{name?: string, is_active?: bool, default_currency?: string|null, default_expiry_days?: int|null, minimum_redemption_points?: int|null, maximum_redemption_points?: int|null}  $params
     */
    public function update(array $params): Program
    {
        return $this->object(Program::class, $this->requestor->request('PATCH', '/program', body: $params));
    }
}
