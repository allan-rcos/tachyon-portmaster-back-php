<?php

declare(strict_types=1);

namespace API\Bootstrap;

use API\Config\ApiConfig;
use API\Config\BootConfig;
use API\Config\JwtConfig;
use Domain\Config\DomainConfig;
use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;
use RuntimeException;

/**
 * The mutable accumulator the chain fills in, one config group per link.
 *
 * Deliberately not the {@see BootConfig} itself: that one is `readonly` and must
 * be complete. This is the half-built state that only exists while the chain
 * runs, and it is discarded as soon as {@see toBootConfig()} is called.
 */
final class BootDraft
{
    public ?ApiConfig $api = null;
    public ?DomainConfig $domain = null;
    public ?DatabaseConfig $database = null;
    public ?JwtConfig $jwt = null;
    public ?LogConfig $log = null;

    /**
     * Freezes the draft into the immutable boot config.
     *
     * @throws RuntimeException when a link failed to contribute its group — a
     *                          wiring mistake in the chain, not a bad `.env`.
     */
    public function toBootConfig(): BootConfig
    {
        return new BootConfig(
            api: $this->api ?? throw self::missing('api'),
            domain: $this->domain ?? throw self::missing('domain'),
            database: $this->database ?? throw self::missing('database'),
            jwt: $this->jwt ?? throw self::missing('jwt'),
            log: $this->log ?? throw self::missing('log'),
        );
    }

    private static function missing(string $group): RuntimeException
    {
        return new RuntimeException(
            "Boot configuration group \"$group\" was never set: its chain link is missing from DotEnvStarter.",
        );
    }
}
