<?php

/**
 * List Users Use Case Contract.
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

use App\Queries\User\ListUsersQuery;
use Infra\Query\User\UserListView;
use Shared\Exceptions\Result;

/**
 * Lists users with their roles attached.
 *
 * Follows the list-read shape documented on
 * {@see \App\Services\Interno\ListProductsUseCase}, over the one endpoint that
 * pages by offset rather than by cursor.
 *
 * Guarded by `user:list`, separate from the `user:get` that
 * {@see IGetUserUseCase} declares: enumerating everyone is a broader thing to be
 * allowed than looking up one person.
 *
 * @see ListUsersQuery What it takes.
 * @see \App\Services\Interno\ListUsersUseCase The implementation.
 * @see IGetUserUseCase The single-read sibling.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IListUsersUseCase
{
    /**
     * Reads the page the query asks for.
     *
     * @param  ListUsersQuery  $query  Carries the caller, the page number and
     *                                 the size.
     * @return Result<UserListView> The page, empty when nothing matched; a 403
     *                              or 500 failure. No cursor and no total — the
     *                              endpoint returns a bare list.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function execute(ListUsersQuery $query): Result;
}
