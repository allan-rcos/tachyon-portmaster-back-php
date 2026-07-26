<?php

declare(strict_types=1);

namespace Infra\Database\Pool\Interno;

use OpenSwoole\Core\Coroutine\Client\ClientConfigInterface;
use OpenSwoole\Core\Coroutine\Client\ClientFactoryInterface;

/**
 * Produces {@see PooledPDOClient} instances for the OpenSwoole {@see \OpenSwoole\Core\Coroutine\Pool\ClientPool}.
 */
final class PooledPDOClientFactory implements ClientFactoryInterface
{
    public static function make(ClientConfigInterface $config): PooledPDOClient
    {
        return new PooledPDOClient($config);
    }
}
