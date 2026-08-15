<?php

/**
 * Boot Config.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Config;

use Domain\Config\DomainConfig;
use Infra\Config\CacheConfig;
use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;

/**
 * Composition-root aggregate of every per-layer config VO.
 *
 * Built once by {@see \API\Bootstrap\DotEnvStarter} from the environment and
 * consumed by {@see \API\ApiRegister::execute()}, which forwards each layer's VO
 * down the register chain (api → app → { infra → domain }). Keeping the
 * per-layer VOs separate preserves the isolation rule; this holder only carries
 * them together across the bootstrap boundary.
 *
 * @see \API\Bootstrap\DotEnvStarter What builds it.
 * @see \API\ApiRegister What takes it apart again.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class BootConfig
{
    /**
     * @param  ApiConfig  $api  HTTP server settings.
     * @param  DomainConfig  $domain  Identifier generation settings.
     * @param  DatabaseConfig  $database  Connection and pool settings.
     * @param  JwtConfig  $jwt  Session token and cookie settings.
     * @param  LogConfig  $log  Logging destination and level.
     * @param  CacheConfig  $cache  Shared-cache sizing. The one group read
     *                              *before* the register chain runs — {@see \API\main}
     *                              hands it to {@see \Infra\OpenSwooleExtension}
     *                              in the global context, because the memory it
     *                              describes has to exist before the server
     *                              forks.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ApiConfig $api,
        public DomainConfig $domain,
        public DatabaseConfig $database,
        public JwtConfig $jwt,
        public LogConfig $log,
        public CacheConfig $cache,
    ) {
    }
}
