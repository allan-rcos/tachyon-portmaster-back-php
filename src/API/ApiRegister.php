<?php

/**
 * API Register.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * This is where {@see BootConfig} is taken apart: each layer receives only its
 * own settings, so nothing below the API layer can reach another's.
 *
 * @see AppRegister The next register in the chain.
 * @see IApiProvider What this returns.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ApiRegister
{
    /**
     * Wires every layer and returns the presentation provider.
     *
     * Runs once per worker, at `WorkerStart`, so everything it builds lives for
     * that worker's lifetime and is never rebuilt per request.
     *
     * @param  BootConfig  $config  Every layer's settings, resolved.
     * @param  int  $serverId  This worker's id, used as the snowflake server id
     *                         so two workers cannot mint the same identifier.
     * @return IApiProvider The provider that builds the router.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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
