<?php

/**
 * Get Container Use Case Contract.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services;

use App\Queries\Container\GetContainerQuery;
use Infra\Query\Container\ContainerViewItem;
use Shared\Exceptions\Result;

/**
 * Reads one container by id, without its manifest.
 *
 * Follows the single-read shape documented on
 * {@see \App\Services\Interno\GetProductUseCase}: authorize, run the DQL, turn
 * an absent row into a 404.
 *
 * Guarded by `container:read`, shared with {@see IListContainersUseCase}.
 *
 * @see GetContainerQuery What it takes.
 * @see \App\Services\Interno\GetContainerUseCase The implementation.
 * @see IListContainerSummariesUseCase The read that includes the cargo.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IGetContainerUseCase
{
    /**
     * Reads the container the query names.
     *
     * @param  GetContainerQuery  $query  Carries the caller and the id.
     * @return Result<ContainerViewItem> The container, or 404 when not found; a
     *                                   403 or 500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(GetContainerQuery $query): Result;
}
