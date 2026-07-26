<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Commands\LoginCommand;
use App\Services\ILoginUseCase;
use Domain\Models\IRole;
use Domain\Models\Internal\User;
use Domain\Models\IUser;
use Domain\TableModules\IAuthTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Orchestrates the login: fetch the user, let {@see IAuthTM} judge the password,
 * then load the roles so the returned {@see IUser} carries everything the caller
 * needs (response mapping and token claims).
 */
final readonly class LoginUseCase implements ILoginUseCase
{
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IRoleRepository $roles,
        private IAuthTM $authTM,
    ) {
    }

    public function execute(LoginCommand $command): Result
    {
        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $userResult = $this->users->findByEmail($command->email);
        if (!$userResult->isSuccess()) {
            $this->unitOfWork->rollback();

            // An unknown e-mail must be indistinguishable from a wrong password.
            return self::invalidCredentials();
        }

        /** @var IUser $user */
        $user = $userResult->getValue();

        $authenticated = $this->authTM->login($user, $command->password);
        if (!$authenticated->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($authenticated->getErrorId());
        }

        $rolesResult = $this->roles->findByUserId($user->id);
        if (!$rolesResult->isSuccess()) {
            $this->unitOfWork->rollback();

            return Result::failure($rolesResult->getErrorId());
        }

        /** @var list<IRole> $roles */
        $roles = $rolesResult->getValue();

        $commit = $this->unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success(new User(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            passwordHash: $user->passwordHash,
            roles: $roles,
        ));
    }

    /**
     * @return Result<never>
     */
    private static function invalidCredentials(): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'Invalid e-mail or password.',
            code: 401,
        )));
    }
}
