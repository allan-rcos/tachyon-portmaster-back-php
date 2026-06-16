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

namespace API\Controllers;

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;

/**
 * Controller interface representing the server operations.
 *
 * This interface defines the main endpoints for general server configuration
 * and information queries.
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
     * @param  Request  $request  The incoming HTTP request.
     * @param  Response  $response  The outgoing HTTP response.
     * @param  array<string, string>  $vars  Path variables extracted by FastRoute.
     * @version 0.0.1
     *
     * @api
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     * @since 0.0.1 File creation.
     *
     */
    public function getInfo(
        Request $request,
        Response $response,
        array $vars = []
    ): void;
}