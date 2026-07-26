<?php

declare(strict_types=1);

namespace App\Interno\Provider;

use App\Services\ICreateUserUseCase;
use App\Services\IDeleteUserUseCase;
use App\Services\IGetUserUseCase;
use App\Services\IListUsersUseCase;
use App\Services\Interno\CreateUserUseCase;
use App\Services\Interno\DeleteUserUseCase;
use App\Services\Interno\GetUserUseCase;
use App\Services\Interno\ListUsersUseCase;
use App\Services\Interno\ResetUserPasswordUseCase;
use App\Services\Interno\UpdateUserRolesUseCase;
use App\Services\Interno\UpdateUserUseCase;
use App\Services\IResetUserPasswordUseCase;
use App\Services\IUpdateUserRolesUseCase;
use App\Services\IUpdateUserUseCase;

/**
 * User administration. Self-service lives in {@see AccountProvider}.
 */
final class UserProvider extends FeatureProvider
{
    public function listUsersUseCase(): IListUsersUseCase
    {
        return new ListUsersUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function getUserUseCase(): IGetUserUseCase
    {
        return new GetUserUseCase($this->infra->queryRepository(), $this->registrar());
    }

    public function createUserUseCase(): ICreateUserUseCase
    {
        return new CreateUserUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
            $this->registrar(),
        );
    }

    public function updateUserUseCase(): IUpdateUserUseCase
    {
        return new UpdateUserUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
            $this->registrar(),
        );
    }

    public function deleteUserUseCase(): IDeleteUserUseCase
    {
        return new DeleteUserUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->registrar(),
        );
    }

    public function resetUserPasswordUseCase(): IResetUserPasswordUseCase
    {
        return new ResetUserPasswordUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
            $this->registrar(),
        );
    }

    public function updateUserRolesUseCase(): IUpdateUserRolesUseCase
    {
        return new UpdateUserRolesUseCase(
            $this->infra->unitOfWork(),
            $this->infra->userRepository(),
            $this->registrar(),
        );
    }
}
