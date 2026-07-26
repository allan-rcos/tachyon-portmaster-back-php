<?php

declare(strict_types=1);

namespace App\Services;

use App\Queries\Account\GetAccountQuery;
use Infra\Query\Account\AccountView;
use Shared\Exceptions\Result;

interface IGetAccountUseCase
{
    /**
     * Reads the caller's own profile. Needs no permission: holding a
     * {@see \App\Context\UserContext} already means being authenticated.
     *
     * @return Result<AccountView> The user's profile, or 404 when not found.
     */
    public function execute(GetAccountQuery $query): Result;
}
