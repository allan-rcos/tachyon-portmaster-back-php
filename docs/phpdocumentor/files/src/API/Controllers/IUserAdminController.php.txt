<?php

/**
 * User Admin Controller Contract.
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
 * User administration endpoints (`/users`).
 *
 * Acting on *other* people's accounts, which is what separates these from
 * {@see IAccountController}: the two overlap in what they change, and differ
 * entirely in who may do it.
 *
 * @see IProductController The contract shape these follow.
 * @see IAccountController The same operations performed on yourself.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IUserAdminController
{
    /**
     * `GET /users` — a keyset page of users.
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
     * `POST /users` — registers a user with their initial roles. Answers 201.
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
     * `GET /users/{id}` — one user, or 404.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function get(ServerRequestInterface $request): ResponseInterface;

    /**
     * `PUT /users/{id}` — updates a user's name and email.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function update(ServerRequestInterface $request): ResponseInterface;

    /**
     * `DELETE /users/{id}` — removes a user. Answers 204.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function delete(ServerRequestInterface $request): ResponseInterface;

    /**
     * `PUT /users/{id}/password` — sets a new password without presenting the
     * old one, which is what makes this an administrative act rather than the
     * self-service change on {@see IAccountController::changePassword()}.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function resetPassword(ServerRequestInterface $request): ResponseInterface;

    /**
     * `PUT /users/{id}/roles` — replaces the user's whole role set with the one
     * presented.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function updateRoles(ServerRequestInterface $request): ResponseInterface;
}
