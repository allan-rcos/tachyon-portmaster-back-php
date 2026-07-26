<?php

declare(strict_types=1);

namespace Domain\Config;

/**
 * Domain configuration value object.
 *
 * Carries the architectural knobs the domain needs — currently only the
 * Snowflake id-generation settings consumed by {@see \Domain\ID\Interno\SnowflakeIdGenerator}.
 * The per-worker server id is a runtime value handed to {@see \Domain\DomainRegister}
 * separately, not stored here.
 */
readonly class DomainConfig
{
    public function __construct(
        public int $snowflakeEpoch = 1704067200000, // January 1, 2024
        public int $snowflakeClusterId = 1,
    ) {
    }
}
