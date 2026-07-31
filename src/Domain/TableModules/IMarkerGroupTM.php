<?php

/**
 * Marker Group Table Module Contract.
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

use Domain\Models\IMarkerGroup;
use Shared\Exceptions\Result;

/**
 * The rules a marker-group slug must satisfy.
 *
 * Reached at `WorkerStart`, once per feature that needs a namespace for its
 * flags.
 *
 * @see IMarkerGroup What gets built.
 * @see \Domain\TableModules\Interno\MarkerGroupTM The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IMarkerGroupTM
{
    /**
     * Builds a marker group after validating it.
     *
     * The returned group has `id = 0`: the registry index is assigned later, by
     * {@see \Infra\Repository\IMarkerGroupRepository::add()}.
     *
     * @param  string  $slug  Lower-kebab single token (e.g. `refresh-token`).
     * @return Result<IMarkerGroup> A 422 failure when the slug is malformed.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(string $slug): Result;
}
