<?php

/**
 * Server Controller Contract.
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
 * Controller interface representing the server operations.
 *
 * This interface defines the main endpoints for general server configuration
 * and information queries. Controllers follow the PSR-15/PSR-7 model: they
 * receive the server request and return a response; path variables are read
 * from the request attributes.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IServerController
{
    /**
     * Gets project metadata information.
     *
     * Provides basic definitions identifying the currently running project
     * status.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface The outgoing HTTP response.
     *
     * @api
     *
     * @copyright 2026 Tachyon
     */
    public function getInfo(ServerRequestInterface $request): ResponseInterface;
}
