<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\IChangePasswordUseCase;
use App\Services\IGetAccountUseCase;
use App\Services\Interno\ChangePasswordUseCase;
use App\Services\Interno\GetAccountUseCase;
use App\Services\Interno\UpdateAccountUseCase;
use App\Services\IUpdateAccountUseCase;

/**
 * Self-service: what a signed-in user may do to their **own** account.
 *
 * None of these takes the permission registrar, and that is the point — they
 * declare no permission because being authenticated is the whole requirement.
 * Acting on someone else's account is {@see UserProvider}.
 */
final class AccountProvider extends FeatureProvider
{
    public function getAccountUseCase(): IGetAccountUseCase
    {
        return new GetAccountUseCase($this->infra->queryRepository());
    }

    public function updateAccountUseCase(): IUpdateAccountUseCase
    {
        return new UpdateAccountUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
        );
    }

    public function changePasswordUseCase(): IChangePasswordUseCase
    {
        return new ChangePasswordUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->domain->authTM(),
            $this->domain->userTM(),
        );
    }
}
