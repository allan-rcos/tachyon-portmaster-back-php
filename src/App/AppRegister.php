<?php

/**
 * App Register.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App;

use App\Interno\AppProvider;
use Domain\Config\DomainConfig;
use Domain\DomainRegister;
use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;
use Infra\InfraRegister;
use Infra\IOpenSwooleExtensionProvider;

/**
 * Composition entry point for the application layer.
 *
 * Chains the inner registers — infra, then domain — and embeds both in the app
 * provider. The two are independent now that the password hasher is the domain's
 * own; the order is merely conventional. This is the only place the register
 * order is expressed; presentations call {@see \API\ApiRegister}, which
 * delegates here.
 *
 * @see IAppProvider What it returns.
 * @see \App\Interno\AppProvider What it constructs.
 * @see \Infra\InfraRegister The inner register it chains first.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class AppRegister
{
    /**
     * Composes all three layers and hands back the application's façade.
     *
     * Cheap, like the registers it chains: nothing is constructed beyond the
     * providers themselves and no connection is opened, so this can run before
     * the database is reachable.
     *
     * @param  DomainConfig  $domain  Domain settings.
     * @param  DatabaseConfig  $database  Connection and pool settings.
     * @param  LogConfig  $log  The level to log at.
     * @param  int  $serverId  Identifies this server within the Snowflake id
     *                         scheme; two servers sharing one would mint
     *                         colliding ids.
     * @param  IOpenSwooleExtensionProvider  $extension  The server's shared,
     *                                                   pre-fork resources. Passed
     *                                                   straight through — nothing
     *                                                   in this layer reads it, and
     *                                                   {@see \Infra\Interno\InfraProvider}
     *                                                   is what consumes it.
     * @return IAppProvider Everything the presentation layer is allowed to
     *                      reach.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function execute(
        DomainConfig $domain,
        DatabaseConfig $database,
        LogConfig $log,
        int $serverId,
        IOpenSwooleExtensionProvider $extension,
    ): IAppProvider {
        // Domain first: the infrastructure layer takes its index hasher, which is
        // a domain service, so the provider that owns it has to exist by then.
        $domainProvider = DomainRegister::execute($domain, $serverId);
        $infra = InfraRegister::execute($database, $log, $extension, $domainProvider->indexHasher());

        return new AppProvider($domainProvider, $infra);
    }
}
