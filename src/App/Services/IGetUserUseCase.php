<?php

/**
 * Get User Use Case Contract.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Services;

use App\Queries\User\GetUserQuery;
use Infra\Query\Account\AccountView;
use Shared\Exceptions\Result;

/**
 * Reads another user's profile.
 *
 * Follows the single-read shape documented on
 * {@see \App\Services\Interno\GetProductUseCase}.
 *
 * @see GetUserQuery What it takes.
 * @see \App\Services\Interno\GetUserUseCase The implementation.
 * @see IGetAccountUseCase The self-service counterpart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
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
     * @param  GetUserQuery  $query  Carries the caller and the subject's id.
     * @return Result<AccountView> The profile, or 404 when not found; a 403 or
     *                             500 otherwise.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(GetUserQuery $query): Result;
}
