<?php

declare(strict_types=1);

namespace Domain;

use Domain\Config\DomainConfig;
use Domain\Interno\DomainProvider;

/**
 * Composition entry point for the domain layer.
 *
 * The core has no dependencies to inject: the password hasher its TableModules
 * need is a domain implementation now, built privately by the provider. The
 * runtime `serverId` (the Swoole worker id) parameterises the Snowflake generator.
 */
final class DomainRegister
{
    public static function execute(DomainConfig $config, int $serverId): IDomainProvider
    {
        return new DomainProvider($config, $serverId);
    }
}
