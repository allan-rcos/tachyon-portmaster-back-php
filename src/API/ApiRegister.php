<?php

declare(strict_types=1);

namespace API;

use API\Config\BootConfig;
use API\Interno\ApiProvider;
use App\AppRegister;

/**
 * Composition entry point for the presentation layer — the outermost register.
 *
 * Called by {@see \API\main} at WorkerStart with the fully-resolved
 * {@see BootConfig} and the worker id (the Snowflake server id). It chains the
 * application register (which in turn chains infra + domain) and hands back the
 * provider that builds the ready-to-use router.
 */
final class ApiRegister
{
    public static function execute(BootConfig $config, int $serverId): IApiProvider
    {
        $app = AppRegister::execute(
            $config->domain,
            $config->database,
            $config->log,
            $serverId,
        );

        return new ApiProvider($app, $config->api, $config->jwt);
    }
}
