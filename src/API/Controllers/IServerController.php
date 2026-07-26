<?php

/**
 * Controller Interface Module.
 *
 * A module defining the server operations contract.
 *
 * @category Api\Controllers
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
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
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
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
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     */
    public function getInfo(ServerRequestInterface $request): ResponseInterface;
}
