<?php

/**
 * Permission Table Module Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\TableModules;

use Domain\Models\IPermission;
use Shared\Exceptions\Result;

/**
 * The rules a permission slug must satisfy.
 *
 * Reached once per guarded use case per worker, at `WorkerStart` — the
 * application layer invents the slugs and this decides whether they are
 * well-formed.
 *
 * @see IPermission What gets built.
 * @see \Domain\TableModules\Interno\PermissionTM The implementation.
 * @see \App\Security\AuthorizesWithPermission Where the slugs come from.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IPermissionTM
{
    /**
     * Builds a permission after validating it.
     *
     * The returned permission has `id = 0`: the registry index is assigned
     * later, by {@see \Infra\Repository\IPermissionRepository::add()}.
     *
     * @param  string  $slug  `domain:action`, lower-kebab on both sides.
     * @return Result<IPermission> A 422 failure when the slug is malformed.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(string $slug): Result;
}
