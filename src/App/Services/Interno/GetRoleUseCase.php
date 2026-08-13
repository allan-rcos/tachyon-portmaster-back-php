<?php

/**
 * Get Role Use Case.
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

use App\Queries\Role\GetRoleQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Services\IGetRoleUseCase;
use Infra\Query\Interno\GetRoleDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Role\RoleViewItem;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Reads one role, if the caller may.
 *
 * Follows the single-read shape documented on {@see GetProductUseCase}.
 *
 * @see IGetRoleUseCase The contract this implements.
 * @see GetProductUseCase The shape.
 * @uses IQueryRepository Runs the query; this layer never builds SQL.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class GetRoleUseCase implements IGetRoleUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `role:list`, shared with {@see ListRolesUseCase} — there is no
     * separate `role:read`.
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
    public function execute(GetRoleQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $result = $this->queries->run(new GetRoleDQL($query->id));
        if (!$result->isSuccess()) {
            return Result::failure($result->getErrorId());
        }

        $item = $result->getValue();
        if (!$item instanceof RoleViewItem) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: "Role with id $query->id not found",
                code: 404,
            )));
        }

        return Result::success($item);
    }
}
