<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\User\GetUserQuery;
use Infra\Query\Account\AccountView;
use Shared\Exceptions\Result;

interface IGetUserUseCase
{
    /**
     * Reads **another** user's profile — guarded by `user:get`.
     *
     * Split from {@see IGetAccountUseCase}, which reads the caller's own profile
     * and needs no permission. One use case cannot serve both: the permission a
     * use case declares is fixed, so sharing it would either lock users out of
     * their own account page or hand every user the admin read.
     *
     * @return Result<AccountView> The profile, or 404 when not found.
     */
    public function execute(GetUserQuery $query): Result;
}
