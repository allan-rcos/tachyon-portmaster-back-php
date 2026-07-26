<?php

declare(strict_types=1);

namespace App\Services\Interno;

use App\Context\UserContext;
use App\Services\IValidateSessionUseCase;
use Domain\Models\IRole;
use Domain\Models\Internal\User;
use Domain\Models\IUser;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

final readonly class ValidateSessionUseCase implements IValidateSessionUseCase
{
    public function __construct(
        private IUnitOfWork $unitOfWork,
        private IUserRepository $users,
        private IRoleRepository $roles,
    ) {
    }

    public function execute(UserContext $context): Result
    {
        $begin = $this->unitOfWork->begin();
        if (!$begin->isSuccess()) {
            return Result::failure($begin->getErrorId());
        }

        $found = $this->users->findById($context->id);
        if (!$found->isSuccess()) {
            $this->unitOfWork->rollback();

            return self::invalidSession();
        }

        /** @var IUser $user */
        $user = $found->getValue();

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
    private static function invalidSession(): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'Invalid or expired session.',
            code: 401,
        )));
    }
}
