<?php

declare(strict_types=1);

namespace Domain\TableModules;

use Domain\Models\IPermission;
use Shared\Exceptions\Result;

interface IPermissionTM
{
    /**
     * Builds a permission after validating it.
     *
     * The returned permission has `id = 0`: the registry index is assigned later,
     * by {@see \Infra\Repository\IPermissionRepository::add()}.
     *
     * @param  string  $slug  `domain:action`, lower-kebab on both sides.
     * @return Result<IPermission> Failure 422 when the slug is malformed.
     */
    public function create(string $slug): Result;
}
