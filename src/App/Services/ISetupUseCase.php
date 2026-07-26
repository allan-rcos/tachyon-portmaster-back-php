<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\SetupCommand;
use Domain\Models\IUser;
use Shared\Exceptions\Result;

/**
 * Creates the first user of a deployment, together with a role granting
 * everything.
 *
 * **Why this exists at all.** Every other way into the system is guarded by a
 * permission, permissions are held by roles, and roles are assigned by a user
 * who already has `user:create`. On an empty database that is a closed loop:
 * nobody can be created because nobody exists to do the creating. This is the
 * one door that opens from the outside.
 *
 * **Why it is safe to leave unauthenticated.** It refuses the moment the system
 * has any user at all, so the window is the interval between the first migration
 * and the first successful call — and whoever wins that race *is* the operator,
 * by definition of having reached a freshly deployed system first. Closing it
 * permanently after one use is what keeps it from being a backdoor.
 *
 * The role it creates is granted every permission currently in the registry, not
 * a hard-coded list: the registry is filled at WorkerStart from the use cases
 * themselves, so a permission added later is included without anyone remembering
 * to update this.
 */
interface ISetupUseCase
{
    /**
     * @return Result<IUser> The created user with their role, or **409** when the
     *                       system already has a user — a state conflict, not a
     *                       bad request, and deliberately not a 403: there is no
     *                       credential that would make it succeed.
     */
    public function execute(SetupCommand $command): Result;
}
