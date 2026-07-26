<?php

declare(strict_types=1);

namespace App;

use App\Interno\AppProvider;
use Domain\Config\DomainConfig;
use Domain\DomainRegister;
use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;
use Infra\InfraRegister;

/**
 * Composition entry point for the application layer.
 *
 * Chains the inner registers — infra, then domain — and embeds both in the app
 * provider. The two are independent now that the password hasher is the domain's
 * own; the order is merely conventional. This is the only place the register
 * order is expressed; presentations call {@see \API\ApiRegister}, which
 * delegates here.
 */
final class AppRegister
{
    public static function execute(
        DomainConfig $domain,
        DatabaseConfig $database,
        LogConfig $log,
        int $serverId,
    ): IAppProvider {
        $infra = InfraRegister::execute($database, $log);
        $domainProvider = DomainRegister::execute($domain, $serverId);

        return new AppProvider($domainProvider, $infra);
    }
}
