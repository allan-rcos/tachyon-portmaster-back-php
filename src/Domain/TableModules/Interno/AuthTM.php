<?php

namespace Domain\TableModules\Interno;

use Domain\Models\IUser;
use Domain\TableModules\IAuthTM;
use Domain\Security\ISecureHasher;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class AuthTM implements IAuthTM
{
    public function __construct(
        private ISecureHasher $passwordHasher,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function login(IUser $user, string $password): Result
    {
        if (!$this->passwordHasher->verify($password, $user->passwordHash)) {
            return Result::failure(Leaf::newError(new LeafContext(
                message: 'Invalid e-mail or password.',
                code: 401,
            )));
        }

        return Result::void();
    }
}
