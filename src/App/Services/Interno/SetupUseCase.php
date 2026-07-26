<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\SetupCommand;
use App\Services\ISetupUseCase;
use Domain\Models\IPermission;
use Domain\Models\IRole;
use Domain\Models\Internal\User;
use Domain\Models\IUser;
use Domain\TableModules\IRoleTM;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IPermissionRepository;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * The one use case that uses {@see AuthorizesWithPermission} nowhere.
 *
 * Its guard is {@see IUserRepository::hasAny()} instead — see
 * {@see ISetupUseCase} for why that is the right guard and not a missing one.
 * Everything happens in a single boundary, so a deployment cannot end up with a
 * role and no user, or a user who owns nothing.
 */
final readonly class SetupUseCase implements ISetupUseCase
{
    private const string ROLE_NAME = 'Administrator';

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IRoleRepository $roles,
        private IPermissionRepository $permissions,
        private IUserTM $userTM,
        private IRoleTM $roleTM,
    ) {
    }

    public function execute(SetupCommand $command): Result
    {
        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $populated = $this->users->hasAny();
        if (!$populated->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($populated->getErrorId());
        }

        if ($populated->getValue() === true) {
            $this->unitOfWork->rollback();

            return Result::failure(Leaf::newError(new LeafContext(
                message: 'This system has already been set up.',
                code: 409,
            )));
        }

        // Read from the registry rather than a literal list, so a permission
        // introduced by a future use case is granted here without anyone
        // remembering to come back and add it.
        /** @var list<IPermission> $registered */
        $registered = $this->permissions->all()->toArray();

        $slugs = array_map(
            static fn (IPermission $permission): string => $permission->slug,
            $registered,
        );

        $builtRole = $this->roleTM->create(self::ROLE_NAME, $slugs);
        if (!$builtRole->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($builtRole->getErrorId());
        }

        /** @var IRole $role */
        $role = $builtRole->getValue();

        $insertedRole = $this->roles->insert($role);
        if (!$insertedRole->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($insertedRole->getErrorId());
        }

        $builtUser = $this->userTM->create(
            $command->name,
            $command->email,
            $command->password,
            [$role],
        );
        if (!$builtUser->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($builtUser->getErrorId());
        }

        /** @var IUser $user */
        $user = $builtUser->getValue();

        $insertedUser = $this->users->insert($user);
        if (!$insertedUser->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($insertedUser->getErrorId());
        }

        $synced = $this->users->syncRoles($user->id, [$role->id]);
        if (!$synced->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($synced->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success(new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            roles: [$role],
        ));
    }
}
