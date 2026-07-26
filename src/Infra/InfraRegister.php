<?php

declare(strict_types=1);

namespace Infra;

use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;
use Infra\Interno\InfraProvider;

/**
 * Composition entry point for the infrastructure layer. Receives the infra
 * config VOs (resolved from the environment at bootstrap) and returns the
 * provider; the actual pool/connection is opened lazily on first use.
 */
final class InfraRegister
{
    public static function execute(DatabaseConfig $database, LogConfig $log): IInfraProvider
    {
        return new InfraProvider($database, $log);
    }
}
