<?php

/**
 * List Permissions Use Case.
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

use App\Queries\Role\ListPermissionsQuery;
use App\Security\AuthorizesWithPermission;
use App\Services\IListPermissionsUseCase;
use App\Services\IRegisterPermissionUseCase;
use Domain\Models\IPermission;
use Ds\Seq;
use Infra\Repository\IPermissionRepository;
use Infra\Text\SearchKey;
use Shared\Exceptions\Result;

/**
 * Reads the permission catalogue, if the caller may.
 *
 * Unlike {@see ListRolesUseCase} this does not go through
 * {@see \Infra\Query\IQueryRepository}: the registry already owns the table and
 * already exposes a whole-catalogue read, so a DQL would only re-state the
 * table name in a second place. There is no cursor and no aggregate to compute,
 * which is what the query side exists for.
 *
 * The search filter is applied here rather than in SQL for the same reason the
 * listing is unpaged — the catalogue is tens of rows, already in memory by the
 * time it is filtered.
 *
 * @see IListPermissionsUseCase The contract this implements.
 * @uses IPermissionRepository The registry filled at WorkerStart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ListPermissionsUseCase implements IListPermissionsUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `permission:list`, which is itself registered in the catalogue
     * this reads.
     *
     * @param  IPermissionRepository  $permissions  The registry to read.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IPermissionRepository $permissions,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'permission:list');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(ListPermissionsQuery $query): Result
    {
        $denied = $this->authorize($query->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $all = $this->permissions->all();

        $needle = $query->search !== null ? SearchKey::of($query->search) : '';
        if ($needle === '') {
            return Result::success($all);
        }

        /** @var Seq<IPermission> $matched */
        $matched = $all->filter(
            static fn (IPermission $permission): bool => str_contains(SearchKey::of($permission->slug), $needle),
        );

        return Result::success($matched);
    }
}
