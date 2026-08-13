<?php

/**
 * Pooled PDO Client Factory.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Database\Pool\Interno;

use OpenSwoole\Core\Coroutine\Client\ClientConfigInterface;
use OpenSwoole\Core\Coroutine\Client\ClientFactoryInterface;

/**
 * Produces {@see PooledPDOClient} instances for the OpenSwoole {@see \OpenSwoole\Core\Coroutine\Pool\ClientPool}.
 *
 * Exists because `ClientPool` takes a factory *class name* rather than a
 * callable, so substituting the client subclass means substituting a factory.
 *
 * @see PooledPDOClient What it produces.
 * @see OpenSwoolePDOClientPool What names this factory.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class PooledPDOClientFactory implements ClientFactoryInterface
{
    /**
     * Builds one client from the pool's shared config.
     *
     * Called by the pool whenever it needs another connection, up to its
     * maximum.
     *
     * @param  ClientConfigInterface  $config  The pool's config; a
     *                                         {@see \OpenSwoole\Core\Coroutine\Client\PDOConfig}
     *                                         in practice.
     * @return PooledPDOClient A client whose raw handle can be lent out.
     *
     * @copyright 2026 Tachyon
     */
    public static function make(ClientConfigInterface $config): PooledPDOClient
    {
        return new PooledPDOClient($config);
    }
}
