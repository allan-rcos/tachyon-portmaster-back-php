<?php

/**
 * Log Level Enum.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Config;

/**
 * The log levels the application can be configured to.
 *
 * The four {@see \Infra\Logging\ILogger} exposes, no more: there is no level
 * here that nothing can write. The string values are Monolog's own level names,
 * so the handler takes a case's value directly with nothing to translate.
 *
 * Ordered from most to least verbose, as declared.
 *
 * @see LogConfig What carries the chosen level.
 * @see \Infra\Logging\MonologFactory What applies it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum ServerConfigLogLevel: string
{
    /**
     * Everything, including diagnostic detail. Development only — the
     * repositories log every statement at this level.
     */
    case DEBUG = 'DEBUG';

    /**
     * Ordinary operation and clients' own mistakes. The default.
     */
    case INFO = 'INFO';

    /**
     * Only the unexpected-but-handled, and worse.
     */
    case WARNING = 'WARNING';

    /**
     * Only failures — every 500 this application returns, and nothing else.
     */
    case ERROR = 'ERROR';
}
