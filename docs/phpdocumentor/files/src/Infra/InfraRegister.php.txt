<?php

/**
 * Infra Register.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra;

use Domain\Security\IIndexHasher;
use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;
use Infra\Interno\InfraProvider;

/**
 * Composition entry point for the infrastructure layer. Receives the infra
 * config VOs (resolved from the environment at bootstrap) and returns the
 * provider; the actual pool/connection is opened lazily on first use.
 *
 * The one place {@see InfraProvider} is named from outside its own namespace,
 * which is what keeps the concrete provider private while still letting the
 * layer be composed.
 *
 * @see IInfraProvider What it returns.
 * @see InfraProvider What it constructs.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class InfraRegister
{
    /**
     * Composes the layer from its config and hands back the provider.
     *
     * Cheap: nothing is constructed and no connection is opened here, so calling
     * it during bootstrap costs nothing even if the database is not up yet.
     *
     * @param  DatabaseConfig  $database  Connection and pool settings.
     * @param  LogConfig  $log  The level to log at.
     * @param  IOpenSwooleExtensionProvider  $extension  The server's pre-fork
     *                                                   resources, allocated
     *                                                   before this ever runs.
     *                                                   See {@see OpenSwooleExtension}.
     * @param  IIndexHasher  $hasher  Turns a cache key into the fixed-width
     *                                digest the shared tables are addressed by.
     * @return IInfraProvider The layer's factory surface.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function execute(
        DatabaseConfig $database,
        LogConfig $log,
        IOpenSwooleExtensionProvider $extension,
        IIndexHasher $hasher,
    ): IInfraProvider {
        return new InfraProvider($database, $log, $extension, $hasher);
    }
}
