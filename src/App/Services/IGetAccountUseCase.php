<?php

/**
 * Get Account Use Case Contract.
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

use App\Queries\Account\GetAccountQuery;
use Infra\Query\Account\AccountView;
use Shared\Exceptions\Result;

/**
 * Reads the caller's own profile.
 *
 * Follows the single-read shape documented on
 * {@see \App\Services\Interno\GetProductUseCase}, with the subject taken from
 * the context rather than from the query.
 *
 * @see GetAccountQuery What it takes.
 * @see \App\Services\Interno\GetAccountUseCase The implementation.
 * @see IGetUserUseCase The administrative counterpart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IGetAccountUseCase
{
    /**
     * Reads the caller's own profile. Needs no permission: holding a
     * {@see \App\Context\UserContext} already means being authenticated.
     *
     * @param  GetAccountQuery  $query  Carries the caller, who is the subject.
     * @return Result<AccountView> The user's profile, or 404 when not found —
     *                             which in practice means the account was
     *                             deleted while the session was live.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(GetAccountQuery $query): Result;
}
