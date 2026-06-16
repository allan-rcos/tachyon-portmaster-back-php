<?php

/**
 * Server Config Environment Enum.
 *
 * Module representing bounded logic variants constants map definitions environments.
 *
 * @category Shared\Config
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

namespace Shared\Config;

/**
 * Enum to map and handle environment setups for configuration scopes.
 *
 * It enforces mapped contexts defining runtime domains variants setups constraints.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
enum ServerConfigEnvironmentEnum: string
{
    case DEVELOPMENT = 'DEV';
    case PRODUCTION = 'PROD';
}
