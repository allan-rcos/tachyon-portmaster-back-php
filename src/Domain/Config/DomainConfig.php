<?php

/**
 * Domain Config.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Config;

/**
 * The domain layer's configuration.
 *
 * Only the Snowflake knobs, today. The per-worker server id is deliberately not
 * here: it is a runtime value, handed to {@see \Domain\DomainRegister} at
 * `WorkerStart`, and storing it in a config object would suggest it could be set
 * from the environment.
 *
 * @see \Domain\ID\Interno\SnowflakeIdGenerator The only consumer.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
readonly class DomainConfig
{
    /**
     * @param  int  $snowflakeEpoch  Milliseconds the Snowflake timestamp counts
     *                               from. Changing it after ids exist breaks
     *                               their ordering.
     * @param  int  $snowflakeClusterId  Deployment identifier, 0–31. Distinct
     *                                   per deployment writing to the same
     *                                   database, or their ids can collide.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $snowflakeEpoch = 1704067200000, // January 1, 2024
        public int $snowflakeClusterId = 1,
    ) {
    }
}
