<?php

/**
 * Server Environment.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Config;

/**
 * Runtime environment variants for the server.
 *
 * Drives environment-specific bootstrap decisions in {@see \API\main} (e.g.
 * enabling opcache/JIT-friendly paths in production).
 *
 * @see ApiConfig What carries the chosen variant.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum ServerConfigEnvironmentEnum: string
{
    /**
     * Local and CI. The default.
     */
    case DEVELOPMENT = 'DEV';

    /**
     * A deployed server.
     */
    case PRODUCTION = 'PROD';
}
