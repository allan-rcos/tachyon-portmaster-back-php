<?php

/**
 * Database SSL Mode Enum.
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
 * How the connection to the database is protected.
 *
 * Three cases rather than a boolean, because "the traffic is encrypted" and
 * "the server is who it claims to be" are different guarantees, bought
 * separately: encryption alone stops a passive listener, and only the
 * certificate check stops an active one.
 *
 * Ordered from least to most strict, as declared. The default is
 * {@see self::DISABLED}, which is the right answer for a database on
 * `127.0.0.1` or on a private subnet — and the wrong one for every managed
 * provider, since they refuse a connection in the clear outright.
 *
 * @see DatabaseConfig What carries the chosen mode.
 * @see \Infra\Database\Pool\Interno\PDOConfigFactory What turns it into PDO
 *      attributes.
 * @see ServerConfigLogLevel The same pattern, for logging.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum DatabaseSslMode: string
{
    /**
     * No TLS — the connection is in the clear. The default, and the
     * behaviour of every release before this setting existed.
     */
    case DISABLED = 'disabled';

    /**
     * Encrypt, but accept whatever certificate the server presents. Defeats
     * a passive listener, not an active one.
     */
    case REQUIRED = 'required';

    /**
     * Encrypt and validate the server certificate against the configured CA
     * bundle. What a managed provider expects.
     */
    case VERIFY_CA = 'verify_ca';
}
