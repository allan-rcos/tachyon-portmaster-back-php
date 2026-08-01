<?php

/**
 * Database Config.
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
 * Database connection and pool settings.
 *
 * Resolved from the environment during bootstrap (see
 * {@see \API\Bootstrap\DotEnvStarter}) and handed to the infra layer through
 * {@see \Infra\InfraRegister}, so no service reads the environment directly.
 *
 * Every field has a default, so a missing variable yields a working local
 * configuration rather than a boot failure. That is convenient in development
 * and worth knowing in production: a mistyped variable name is silently the
 * default, not an error.
 *
 * @see \Infra\Database\Pool\Interno\PDOConfigFactory What turns these into a driver config.
 * @see LogConfig The same pattern, for logging.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class DatabaseConfig
{
    /**
     * @param  string  $host  Database host.
     * @param  int  $port  Database port.
     * @param  string  $name  Schema to connect to.
     * @param  string  $user  Connecting user.
     * @param  string  $password  Their password.
     * @param  string  $charset  Connection charset; the default matches the
     *                           schema's own.
     * @param  int  $poolSize  How many connections the pool may open — the
     *                         ceiling on concurrent transactions in one worker.
     * @param  float  $poolTimeout  Seconds a borrower waits when every
     *                              connection is out before the lease fails
     *                              with a 503.
     * @param  float  $maxLeaseTime  Intended ceiling on how long one lease is
     *                               held. Populated from the environment but not
     *                               currently read by the pool, so nothing
     *                               enforces it.
     * @param  float  $maxIdleTime  Intended ceiling on how long an unused
     *                              connection sits in the pool. Also unread at
     *                              present.
     * @param  DatabaseSslMode  $sslMode  How the connection is protected. The
     *                                    default leaves it in the clear, which
     *                                    is what every release before this
     *                                    field did.
     * @param  string  $sslCa  Path to the CA bundle the server certificate is
     *                         validated against. Only read under
     *                         {@see DatabaseSslMode::VERIFY_CA}.
     * @param  bool  $sslVerifyCn  Whether the certificate's common name has to
     *                             match the host connected to. Also only read
     *                             under {@see DatabaseSslMode::VERIFY_CA}.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public string $host = '127.0.0.1',
        public int $port = 3306,
        public string $name = 'app',
        public string $user = 'root',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public int $poolSize = 16,
        public float $poolTimeout = 5.0,
        public float $maxLeaseTime = 30.0,
        public float $maxIdleTime = 60.0,
        public DatabaseSslMode $sslMode = DatabaseSslMode::DISABLED,
        public string $sslCa = '',
        public bool $sslVerifyCn = true,
    ) {
    }
}
