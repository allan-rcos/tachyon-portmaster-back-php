<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\LoginCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

interface ILoginUseCase
{
    /**
     * Authenticates the credentials carried by the command.
     *
     * @param  LoginCommand  $command  The credentials to verify
     * @return Result<IUser> The authenticated user, with its roles loaded, so the
     *                       caller can map it to a response and mint a token.
     *                       Failure (401) for an unknown e-mail or a wrong
     *                       password — never disclosing which.
     */
    public function execute(LoginCommand $command): Result;
}
