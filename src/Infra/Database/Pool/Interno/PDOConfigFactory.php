<?php

/**
 * PDO Config Factory.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Database\Pool\Interno;

use Infra\Config\DatabaseSslMode;
use OpenSwoole\Core\Coroutine\Client\PDOConfig;
use PDO;

/**
 * Builds a {@see PDOConfig} for the OpenSwoole coroutine PDO client.
 *
 * Kept separate from the pool so the connection parameters can be assembled
 * from environment/config in one place and handed to the DI container.
 *
 * Takes configuration values, not driver attributes: the caller says *what*
 * the connection has to be, and every decision about *how* the driver is told
 * so — the TLS attributes below being the awkward case — is made here. That is
 * the same split {@see \Infra\Logging\MonologFactory} makes with
 * {@see \Infra\Config\ServerConfigLogLevel}.
 *
 * @see OpenSwoolePDOClientPool What consumes the config.
 * @see \Infra\Config\DatabaseConfig Where the values come from.
 * @see DatabaseSslMode The TLS half of them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class PDOConfigFactory
{
    /**
     * @var array<int, string> Pins every connection's session time zone to UTC.
     *
     * The rule this enforces is that **every datetime in the system is UTC**, so
     * that a value written by one process and read by another means the same
     * instant regardless of where either runs. `NOW()` is what the markers and
     * the read cache compute their expiry from and compare against, and a
     * connection inheriting the server's local zone would make those two
     * disagree by the offset.
     *
     * An init command rather than a `SET` on every lease: the driver runs it
     * once per connection, including on the reconnect
     * {@see PooledPDOClient} can trigger underneath the pool, so it costs one
     * statement per connection instead of one per borrow.
     *
     * The server is also started on UTC (`--default-time-zone=+00:00` in the dev
     * stack and the integration harness), which makes this belt and braces
     * rather than the only thing standing between the schema and a local zone.
     *
     * @see docs/database.md Why every datetime is UTC.
     */
    private const array TIME_ZONE_OPTION = [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+00:00'",
    ];

    /**
     * Assembles a MySQL connection config.
     *
     * The only driver the application uses, so it is a named method rather than
     * a parameter — a second driver would be a second method here.
     *
     * @param  string  $host  Database host.
     * @param  int  $port  Database port.
     * @param  string  $dbname  Schema to connect to.
     * @param  string  $username  Connecting user.
     * @param  string  $password  Their password.
     * @param  string  $charset  Connection charset; the default matches the
     *                           schema's `utf8mb4`.
     * @param  DatabaseSslMode  $sslMode  How the connection is protected. The
     *                                    default leaves it in the clear.
     * @param  string  $sslCa  Path to the CA bundle, read only under
     *                         {@see DatabaseSslMode::VERIFY_CA}.
     * @param  bool  $sslVerifyCn  Whether the certificate's common name has to
     *                             match the host, also only under
     *                             {@see DatabaseSslMode::VERIFY_CA}.
     * @param  array<int|string, mixed>  $options  Extra PDO attributes, keyed
     *                                             by the `PDO::*` constants —
     *                                             which are integers, hence the
     *                                             key type. The TLS attributes
     *                                             derived from `$sslMode` win
     *                                             over anything named twice, and
     *                                             so does
     *                                             {@see TIME_ZONE_OPTION}: a
     *                                             caller cannot connect on
     *                                             anything but UTC.
     *                                             Note that the error mode set
     *                                             here does not survive:
     *                                             {@see PooledPDOClient} forces
     *                                             exceptions on every handle it
     *                                             lends out.
     * @return PDOConfig Ready to hand to the pool.
     *
     * @copyright 2026 Tachyon
     */
    public static function mysql(
        string $host,
        int $port,
        string $dbname,
        string $username,
        string $password,
        string $charset = 'utf8mb4',
        DatabaseSslMode $sslMode = DatabaseSslMode::DISABLED,
        string $sslCa = '',
        bool $sslVerifyCn = true,
        array $options = [],
    ): PDOConfig {
        return (new PDOConfig())
            ->withDriver(PDOConfig::DRIVER_MYSQL)
            ->withHost($host)
            ->withPort($port)
            ->withDbname($dbname)
            ->withUsername($username)
            ->withPassword($password)
            ->withCharset($charset)
            ->withOptions(
                array_replace(
                    $options,
                    self::TIME_ZONE_OPTION,
                    self::sslOptions($sslMode, $sslCa, $sslVerifyCn),
                ),
            );
    }

    /**
     * Turns the configured TLS mode into the driver attributes that carry it.
     *
     * The `MYSQL_ATTR_SSL_CIPHER` under {@see DatabaseSslMode::REQUIRED} is
     * load-bearing and looks redundant, so it is the thing not to tidy away.
     * `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false` on its own does **not** ask
     * for a TLS handshake — it only decides what happens to a certificate once
     * one arrives. The driver starts a handshake when an attribute *supplies*
     * TLS material, which for a mode that deliberately has no CA to point at
     * means the cipher list. Measured against MariaDB 11 with
     * `--require-secure-transport=ON`: without the cipher attribute the server
     * rejects the connection as insecure transport; with it, the same
     * connection negotiates `TLS_AES_256_GCM_SHA384`.
     *
     * That measurement is also what settles the other open question — whether
     * OpenSwoole's coroutine `PDOConfig` honours `PDO::MYSQL_ATTR_SSL_*` at all
     * under the coroutine hook. It does: `VERIFY_CA` refuses a certificate that
     * does not chain to the configured CA, which is only possible if the
     * attributes reached the driver.
     *
     * @param  DatabaseSslMode  $mode  The configured mode.
     * @param  string  $ca  CA bundle path, meaningful only under `verify_ca`.
     * @param  bool  $verifyCn  Name check, meaningful only under `verify_ca`.
     * @return array<int, string|bool> Keyed by `PDO::MYSQL_ATTR_*`, empty when
     *                                 TLS is off — which leaves the pool
     *                                 configured exactly as it was before this
     *                                 setting existed.
     *
     * @copyright 2026 Tachyon
     */
    private static function sslOptions(
        DatabaseSslMode $mode,
        string $ca,
        bool $verifyCn,
    ): array {
        return match ($mode) {
            DatabaseSslMode::DISABLED => [],
            DatabaseSslMode::REQUIRED => [
                PDO::MYSQL_ATTR_SSL_CIPHER => 'DEFAULT',
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            ],
            DatabaseSslMode::VERIFY_CA => [
                PDO::MYSQL_ATTR_SSL_CA => $ca,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $verifyCn,
            ],
        };
    }
}
