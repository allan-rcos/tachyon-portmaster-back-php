<?php

/**
 * Account Controller Contract.
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
 * Self-service account endpoints (`/account`) — always operate on the caller.
 *
 * No action takes an id: the subject is whoever holds the session cookie. That
 * is also why the use cases behind them declare no permission — acting on
 * yourself needs no grant beyond being signed in.
 *
 * @see IProductController The contract shape these follow.
 * @see IUserAdminController The same operations performed on somebody else.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IAccountController
{
    /**
     * `GET /account` — the caller's own profile, roles included.
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
     * `PUT /account` — updates the caller's own name and email.
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
     * `PUT /account/password` — changes the caller's own password, which
     * requires presenting the current one.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function changePassword(ServerRequestInterface $request): ResponseInterface;
}
