<?php

/**
 * API Chain.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Bootstrap\Chain;

use API\Bootstrap\BootDraft;
use API\Bootstrap\DotEnvVariables;
use API\Bootstrap\EnvSource;
use API\Config\ApiConfig;
use API\Config\ServerConfigEnvironmentEnum;

/**
 * Reads the HTTP server variables into an {@see ApiConfig}.
 *
 * The link shape, written out once here and followed by every other link: build
 * a default-constructed VO, then read each variable with that VO's own field as
 * the fallback. The defaults therefore live in the config VO alone — this class
 * never restates one, and adding a field with a default needs no change here
 * beyond the line that reads it.
 *
 * @see DotEnvChain The chain contract and why it is a chain.
 * @see \API\Bootstrap\DotEnvVariables The variables read.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class ApiChain extends DotEnvChain
{
    /**
     * Fills the draft's `api` slot from `APP_HOST`, `APP_PORT`,
     * `APP_WORKER_NUM` and `ENVIRONMENT`.
     *
     * @param  EnvSource  $env  Reader over the loaded environment.
     * @param  BootDraft  $draft  Accumulator to write the API group into.
     *
     * @copyright 2026 Tachyon
     */
    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new ApiConfig();

        $draft->api = new ApiConfig(
            host: $env->string(DotEnvVariables::APP_HOST, $defaults->host),
            port: $env->int(DotEnvVariables::APP_PORT, $defaults->port),
            workerNum: $env->int(DotEnvVariables::APP_WORKER_NUM, $defaults->workerNum),
            environment: $env->enum(
                DotEnvVariables::ENVIRONMENT,
                ServerConfigEnvironmentEnum::class,
                $defaults->environment,
            ),
        );
    }
}
