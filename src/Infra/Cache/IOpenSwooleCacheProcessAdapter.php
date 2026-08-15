<?php

/**
 * OpenSwoole Cache Process Adapter Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Cache;

/**
 * Hands out slices of the cache the server process holds.
 *
 * One method, on purpose. The adapter is not what a repository talks to — it is
 * what a repository is *given* its {@see ICacheProcessDatabase} by, once, in
 * {@see \Infra\Interno\InfraProvider}. Putting `get`/`put`/`clean` here instead
 * would have made every caller name its own key on every call, and the key is a
 * property of the database rather than of the operation.
 *
 * The storage itself is allocated by the implementation's static `attach()`,
 * called from {@see \Infra\OpenSwooleExtension} in the **global** context —
 * before `$server->start()` forks. That timing is the whole point: a table
 * allocated inside `WorkerStart` would be one table per worker, which is the bug
 * ADR 0002 records. Instances of this interface are built afterwards, per
 * worker, and find the storage already there.
 *
 * @see ICacheProcessDatabase What this returns.
 * @see \Infra\IOpenSwooleExtensionProvider What exposes it.
 * @see \Infra\Cache\Interno\OpenSwooleCacheProcessAdapter The implementation.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the cache lives here.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IOpenSwooleCacheProcessAdapter
{
    /**
     * The slice of the cache described by this config.
     *
     * Cheap and repeatable: a database is a key, a TTL and a reference to
     * storage that already exists, so calling this twice with the same config
     * costs nothing and yields two objects that address the same entries.
     *
     * @param  CacheProcessDatabaseConfig  $config  Which slice, and how long its
     *                                              entries live by default.
     * @return ICacheProcessDatabase Ready to use; no connection is opened and
     *                               nothing is allocated.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function database(CacheProcessDatabaseConfig $config): ICacheProcessDatabase;
}
