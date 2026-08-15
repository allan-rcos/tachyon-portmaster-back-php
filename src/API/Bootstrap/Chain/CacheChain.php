<?php

/**
 * Cache Chain.
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
use Infra\Config\CacheConfig;

/**
 * Reads the shared cache's sizing out of the environment.
 *
 * The deployment half only. How long an entry stays valid is
 * {@see \Infra\Config\CacheLimits}, which reads nothing and is not configurable:
 * a TTL is a decision about how stale an answer may be, and that does not change
 * between one machine and the next.
 *
 * Its output is consumed earlier than every other link's. {@see \API\main} hands
 * {@see BootDraft::$cache} to {@see \Infra\OpenSwooleExtension} in the global
 * context, before the server forks, while the rest of {@see \API\Config\BootConfig}
 * is not read until `WorkerStart`.
 *
 * @see DatabaseChain The same shape, for the connection pool.
 * @see CacheConfig What it produces.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class CacheChain extends DotEnvChain
{
    /**
     * Fills {@see BootDraft::$cache}.
     *
     * @param  EnvSource  $env  Where the values come from, and where a malformed
     *                          one is recorded.
     * @param  BootDraft  $draft  The draft being assembled.
     *
     * @copyright 2026 Tachyon
     */
    protected function process(EnvSource $env, BootDraft $draft): void
    {
        $defaults = new CacheConfig();

        $draft->cache = new CacheConfig(
            entries: $env->int(DotEnvVariables::APP_CACHE_ENTRIES, $defaults->entries),
            payloadBytes: $env->int(DotEnvVariables::APP_CACHE_PAYLOAD_BYTES, $defaults->payloadBytes),
            sweepIntervalMs: $env->int(DotEnvVariables::APP_CACHE_SWEEP_INTERVAL, $defaults->sweepIntervalMs),
            highWater: $env->float(DotEnvVariables::APP_CACHE_HIGH_WATER, $defaults->highWater),
        );
    }
}
