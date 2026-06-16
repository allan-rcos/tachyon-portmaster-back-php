<?php

/**
 * Expected Environment Variables Enum.
 *
 * A module enumerating system supported dynamic environment properties context variables.
 *
 * @category Infra\Core
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

namespace Infra\Core;

/**
 * Enum outlining the expected environment variables for the application.
 *
 * Encapsulating constraints parameters context values map definition keys explicitly setup definition rules constraints.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
enum DotEnvVariables: string
{
    case APP_HOST = 'APP_HOST';
    case APP_PORT = 'APP_PORT';
    case APP_WORKER_NUM = 'APP_WORKER_NUM';
    case SNOWFLAKE_EPOCH = 'SNOWFLAKE_EPOCH';
    case SNOWFLAKE_MACHINE_ID = 'SNOWFLAKE_MACHINE_ID';
    case ENVIRONMENT = 'ENVIRONMENT';
    case LOG_LEVEL = 'LOG_LEVEL';
}
