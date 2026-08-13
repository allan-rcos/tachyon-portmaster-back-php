<?php

/**
 * List Roles Use Case.
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
use App\Queries\Role\ListRolesQuery;
use App\Services\IListRolesUseCase;
use Infra\Query\Interno\ListRolesDQL;
use Infra\Query\IQueryRepository;
use Shared\Exceptions\Result;

/**
 * Lists roles, if the caller may.
 *
 * Follows the list-read shape documented on {@see ListProductsUseCase}.
 *
 * @see IListRolesUseCase The contract this implements.
 * @see ListProductsUseCase The shape.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ListRolesUseCase implements IListRolesUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `role:list`, shared with {@see GetRoleUseCase}.
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
        $this->permission = $this->declarePermission($registrar, 'role:list');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ListRolesQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        return $this->queries->run(new ListRolesDQL(
            cursor: $query->cursor,
            limit: $query->limit,
            search: $query->search,
        ));
    }
}
