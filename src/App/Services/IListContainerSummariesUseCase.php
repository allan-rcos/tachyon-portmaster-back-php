<?php

/**
 * List Container Summaries Use Case Contract.
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

use App\Queries\Container\ListContainerSummariesQuery;
use Infra\Query\Container\ContainerSummaryListView;
use Shared\Exceptions\Result;

/**
 * Lists containers with their manifests and recent telemetry attached.
 *
 * Follows the list-read shape documented on
 * {@see \App\Services\Interno\ListProductsUseCase}.
 *
 * Guarded by `container:summary` rather than `container:read`: the summary
 * exposes what every container is carrying, which is more than a caller allowed
 * to see the containers themselves is necessarily allowed to know.
 *
 * @see ListContainerSummariesQuery What it takes.
 * @see \App\Services\Interno\ListContainerSummariesUseCase The implementation.
 * @see IListContainersUseCase The cheaper listing, without cargo.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IListContainerSummariesUseCase
{
    /**
     * Reads the page the query asks for, narrowed to one container when the
     * query names an id.
     *
     * @param  ListContainerSummariesQuery  $query  Carries the caller, an
     *                                              optional id and the paging.
     * @return Result<ContainerSummaryListView> The page, empty when nothing
     *                                          matched; a 403 or 500 failure.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(ListContainerSummariesQuery $query): Result;
}
