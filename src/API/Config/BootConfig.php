<?php

declare(strict_types=1);

namespace API\Config;

use Domain\Config\DomainConfig;
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
 */
readonly class BootConfig
{
    public function __construct(
        public ApiConfig $api,
        public DomainConfig $domain,
        public DatabaseConfig $database,
        public JwtConfig $jwt,
        public LogConfig $log,
    ) {
    }
}
