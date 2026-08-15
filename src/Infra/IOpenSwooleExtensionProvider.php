<?php

/**
 * OpenSwoole Extension Provider Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra;

use Domain\Security\IIndexHasher;
use Infra\Cache\IOpenSwooleCacheProcessAdapter;

/**
 * Everything the server holds outside a worker.
 *
 * {@see IInfraProvider} builds what a worker owns; this builds what the *server*
 * owns. The difference is the fork. A provider from that one is materialised in
 * `WorkerStart` and is private to one worker; everything reachable from here was
 * allocated before `$server->start()`, so all workers see the same instance of
 * it.
 *
 * That is what makes it the right home for shared resources in general, and the
 * reason it is an interface with room in it rather than a single accessor: a
 * queue, a rate limiter or a counter that has to be one thing across all workers
 * belongs here too, allocated the same way. Today it holds one thing.
 *
 * It is handed *down* the register chain — {@see \API\ApiRegister} to
 * {@see \App\AppRegister} to {@see InfraRegister} — because
 * {@see \Infra\Interno\InfraProvider} is what needs it, and the composition root
 * is the only place that can obtain it.
 *
 * @see OpenSwooleExtension What produces this.
 * @see IOpenSwooleCacheProcessAdapter The one resource it currently exposes.
 * @see IInfraProvider The per-worker counterpart.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IOpenSwooleExtensionProvider
{
    /**
     * The adapter that hands out slices of the shared cache.
     *
     * Built per call rather than memoized: an adapter is a handle on memory that
     * already exists, so constructing one allocates nothing.
     *
     * The hasher arrives here rather than being built alongside the tables
     * because it is a domain service, and this provider is constructed before
     * the fork — before any domain provider exists. It is the caller in the
     * infrastructure layer, which does have one, that supplies it.
     *
     * @param  IIndexHasher  $hasher  Turns a cache key into the fixed-width
     *                                digest the shared tables are addressed by.
     * @return IOpenSwooleCacheProcessAdapter Ready to answer
     *                                        {@see IOpenSwooleCacheProcessAdapter::database()}.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function cacheProcessAdapter(IIndexHasher $hasher): IOpenSwooleCacheProcessAdapter;
}
