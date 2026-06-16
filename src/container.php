<?php

/**
 * Dependency Injection Registry.
 *
 * Main bindings container structure loading configuration maps.
 *
 * @category Core Configuration
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

use API\Controllers\Interno\ServerController;
use API\Controllers\IServerController;

/**
 * Dependency Injection container bindings mapping definitions.
 *
 * @return array<class-string, mixed> Implementation resolution definitions.
 */
return [
    IServerController::class => DI\get(ServerController::class),
];