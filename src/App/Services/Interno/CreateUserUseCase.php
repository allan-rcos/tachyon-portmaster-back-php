<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Security\AuthorizesWithPermission;
use App\Services\IRegisterPermissionUseCase;
use App\Commands\User\CreateUserCommand;
use App\Services\ICreateUserUseCase;
use Domain\Models\IUser;
use Domain\TableModules\IUserTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class CreateUserUseCase implements ICreateUserUseCase
{
    use AuthorizesWithPermission;

    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IUserTM $userTM,
        IRegisterPermissionUseCase $registrar,
    ) {
        $this->permission = $this->declarePermission($registrar, 'user:create');
    }

    public function execute(CreateUserCommand $command): Result
    {
        $denied = $this->authorize($command->context);
        if (!$denied->isSuccess()) {
            return Result::failure($denied->getErrorId());
        }

        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        // Enforce e-mail uniqueness explicitly so it surfaces as 409, not 500.
        $existing = $this->users->findByEmail($command->email);
        if ($existing->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure(Leaf::newError(new LeafContext(
                message: 'A user with this e-mail already exists.',
                code: 409,
            )));
        }
        $error = Leaf::getError($existing->getErrorId());
        if ($error !== null && $error->code !== 404) {
            $this->unitOfWork->rollback();

            return $existing;
        }

        $built = $this->userTM->create($command->name, $command->email, $command->initialPassword, []);
        if (!$built->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($built->getErrorId());
        }

        /** @var IUser $user */
        $user = $built->getValue();

        $inserted = $this->users->insert($user);
        if (!$inserted->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($inserted->getErrorId());
        }

        $synced = $this->users->syncRoles($user->id, $command->roleIds);
        if (!$synced->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($synced->getErrorId());
        }

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($user);
    }
}
