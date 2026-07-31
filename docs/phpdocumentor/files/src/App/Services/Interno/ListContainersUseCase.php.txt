<?php

/**
 * List Containers Use Case.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Queries\Container\ListContainersQuery;
use App\Services\IListContainersUseCase;
use Infra\Query\Interno\ListContainersDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

/**
 * Lists containers, if the caller may.
 *
 * Follows the list-read shape documented on {@see ListProductsUseCase}: the
 * query's parameters, including both status filters, are passed to the DQL
 * untouched.
 *
 * @see IListContainersUseCase The contract this implements.
 * @see ListProductsUseCase The shape.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ListContainersUseCase implements IListContainersUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `container:read`, shared with {@see GetContainerUseCase}.
     *
     * @param  IQueryRepository  $queries  The read-side runner.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IQueryRepository $queries,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'container:read');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ListContainersQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListContainersDQL(
            cursor: $query->cursor,
            limit: $query->limit,
            search: $query->search,
            status: $query->status,
            statusIn: $query->statusIn,
        ));
    }
}
