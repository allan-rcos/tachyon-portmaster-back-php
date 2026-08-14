<?php

/**
 * User Provider.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
/**
 * Builds the user-administration use cases.
 *
 * The administrative side only — reading and changing *other* people's accounts.
 * A caller acting on their own is {@see AccountProvider}'s, and the two are kept
 * apart because their use cases differ in exactly one thing: whether a
 * permission is declared.
 *
 * See {@see FeatureProvider} for why the wiring is split this way and why
 * nothing here is memoized.
 *
 * @see FeatureProvider The base, and the registrar.
 * @see AccountProvider The self-service counterpart.
 * @see \App\Interno\AppProvider What re-exports these.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class UserProvider extends FeatureProvider
{
    /**
     * Builds the {@see IListUsersUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function listUsersUseCase(): IListUsersUseCase
    {
        return new ListUsersUseCase(
            $this->infra->queryRepository(),
            $this->infra->viewCacheRepository(),
            $this->events,
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IGetUserUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function getUserUseCase(): IGetUserUseCase
    {
        return new GetUserUseCase($this->infra->queryRepository(), $this->registrar());
    }

    /**
     * Builds the {@see ICreateUserUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function createUserUseCase(): ICreateUserUseCase
    {
        return new CreateUserUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IUpdateUserUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function updateUserUseCase(): IUpdateUserUseCase
    {
        return new UpdateUserUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IDeleteUserUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function deleteUserUseCase(): IDeleteUserUseCase
    {
        return new DeleteUserUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->userRepository(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IResetUserPasswordUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function resetUserPasswordUseCase(): IResetUserPasswordUseCase
    {
        return new ResetUserPasswordUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->userRepository(),
            $this->domain->userTM(),
            $this->registrar(),
        );
    }

    /**
     * Builds the {@see IUpdateUserRolesUseCase} implementation.
     *
     * @copyright 2026 Tachyon
     */
    public function updateUserRolesUseCase(): IUpdateUserRolesUseCase
    {
        return new UpdateUserRolesUseCase(
            $this->infra->unitOfWork(),
            $this->infra->viewCacheRepository(),
            $this->infra->userRepository(),
            $this->registrar(),
        );
    }
}
