<?php

declare(strict_types=1);

namespace Domain\TableModules;

use Domain\Models\IMarkerGroup;
use Shared\Exceptions\Result;

interface IMarkerGroupTM
{
    /**
     * Builds a marker group after validating it.
     *
     * The returned group has `id = 0`: the registry index is assigned later, by
     * {@see \Infra\Repository\IMarkerGroupRepository::add()}.
     *
     * @param  string  $slug  Lower-kebab single token (e.g. `refresh-token`).
     * @return Result<IMarkerGroup> Failure 422 when the slug is malformed.
     */
    public function create(string $slug): Result;
}
