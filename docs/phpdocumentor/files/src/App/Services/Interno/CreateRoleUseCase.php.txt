<?php

/**
 * Create Role Use Case.
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
use App\Commands\Role\CreateRoleCommand;
use App\Services\ICreateRoleUseCase;
use Domain\Models\IRole;
use Domain\TableModules\IRoleTM;
use Ds\Map;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IPermissionRepository;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Creates a role granting a set of permissions, if the caller may and the domain
 * allows it.
 *
 * Follows the write shape documented on {@see CreateProductUseCase}.
 *
 * @see ICreateRoleUseCase The contract this implements.
 * @see CreateProductUseCase The shape.
 * @uses IUnitOfWork The boundary this opens and closes.
 * @uses IRoleRepository Persists what the table module built.
 * @uses IRoleTM Validates the role's own shape — its name, and the list as a list.
 * @uses IPermissionRepository Answers which of the requested slugs are not
 *                             registered, which is a question about state and so
 *                             not the table module's to answer.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class CreateRoleUseCase implements ICreateRoleUseCase
{
    use AuthorizesWithPermission;

    /**
     * Declares `role:create`.
     *
     * @param  IUnitOfWork  $unitOfWork  The boundary; never the connection.
     * @param  IViewCacheRepository  $views  Told to drop the group once the
     *                                       commit has landed.
     * @param  IRoleRepository  $roles  Where the built role is written.
     * @param  IRoleTM  $roleTM  Validates and builds it.
     * @param  IPermissionRepository  $permissions  Consulted so a role cannot
     *                                              grant a permission nothing
     *                                              declared.
     * @param  IRegisterPermissionUseCase  $registrar  Consulted once, here.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IViewCacheRepository $views,
        private IRoleRepository $roles,
        private IRoleTM $roleTM,
        private IPermissionRepository $permissions,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'role:create');
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function execute(CreateRoleCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        // Before the table module, which validates the role's own shape but
        // knows nothing about which permissions happen to be registered.
        $unknown = $this->permissions->unknown($command->permissions);
        if ($unknown !== []) {
            $this->unitOfWork->rollback();

            return self::unknownPermissions($unknown);
        }

        $built = $this->roleTM->create($command->name, $command->permissions);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IRole $role */
        $role = $built->getValue();

        $inserted = $this->roles->insert($role);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        // After the commit, never before: a read in between would repopulate
        // the cache from the state this write replaces.
        $this->views->invalidate(ViewCacheGroup::Role);

        return Result::success($role);
    }

    /**
     * Refuses a role naming permissions no use case ever declared.
     *
     * A 422: the request is well-formed and the rule it breaks is that a role may
     * only grant what exists. Every offending slug is named, so a client fixes
     * its payload in one round trip rather than discovering them one at a time.
     *
     * The check lives here rather than in {@see \Domain\TableModules\IRoleTM}
     * because it is a question about state — what happens to be registered right
     * now — and the domain layer neither reaches the catalogue nor should.
     *
     * @param  list<string>  $unknown  The slugs that are not registered.
     * @return Result<never> Always a 422 failure.
     *
     * @copyright 2026 Tachyon
     */
    private static function unknownPermissions(array $unknown): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'A role cannot grant a permission that does not exist.',
            details: new Map(['unknown' => implode(', ', $unknown)]),
            code: 422,
        )));
    }
}
