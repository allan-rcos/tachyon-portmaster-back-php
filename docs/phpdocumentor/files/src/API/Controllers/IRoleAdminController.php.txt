<?php

/**
 * Role Admin Controller Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Role administration endpoints (`/roles`).
 *
 * There is no update or delete: a role's name is fixed once created, and the
 * only thing about it that changes is which permissions it grants.
 *
 * @see IProductController The contract shape these follow.
 * @see IUserAdminController Where a user is given a role.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IRoleAdminController
{
    /**
     * `GET /roles` — a keyset page of roles.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function list(ServerRequestInterface $request): ResponseInterface;

    /**
     * `POST /roles` — registers a role. Answers 201.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(ServerRequestInterface $request): ResponseInterface;

    /**
     * `PUT /roles/{id}/permissions` — replaces the role's whole permission set
     * with the one presented.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function syncPermissions(ServerRequestInterface $request): ResponseInterface;
}
