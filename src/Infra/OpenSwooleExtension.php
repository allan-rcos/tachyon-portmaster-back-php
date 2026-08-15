<?php

/**
 * OpenSwoole Extension.
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

use Infra\Cache\Interno\OpenSwooleCacheProcessAdapter;
use Infra\Config\CacheConfig;
use Infra\Config\LogConfig;
use Infra\Interno\OpenSwooleExtensionProvider;
use Infra\Logging\MonologFactory;
use OpenSwoole\Server;

/**
 * Composition entry point for the infrastructure layer's **pre-fork** half.
 *
 * {@see InfraRegister} composes what a worker owns, at `WorkerStart`. This
 * composes what the *server* owns, in the global context, before
 * `$server->start()` — and the ordering is the entire reason it exists as a
 * separate entry point. OpenSwoole forks its workers out of the master, so
 * shared memory has to be allocated while there is still only one process to
 * allocate it in. Everything this touches is inherited by every worker; nothing
 * built after the fork can be.
 *
 * That is what closes the "revisit if" both ADR 0002 and ADR 0010 left open. The
 * metadata registries and the read cache were in MariaDB because the object
 * graph is built inside `WorkerStart`, where an `OpenSwoole\Table` would have
 * been one table per worker and an invalidation on worker 2 invisible to worker
 * 3. Allocating here instead makes them one table, full stop.
 *
 * **Anything that has to be one thing across all workers belongs here**, and the
 * shape is deliberately open for it: {@see attach()} answers a provider rather
 * than a cache, so the next shared resource is a method on
 * {@see IOpenSwooleExtensionProvider} and a call inside this one, not a second
 * mechanism.
 *
 * Named from `src/API/main.php` directly, which is the one file allowed to reach
 * across layers — it is the composition root, and it already names
 * {@see MonologFactory} the same way.
 *
 * @see IOpenSwooleExtensionProvider What this returns.
 * @see InfraRegister The post-fork counterpart.
 * @see OpenSwooleCacheProcessAdapter::attach() What actually allocates.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the cache lives here.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class OpenSwooleExtension
{
    /**
     * Allocates the server's shared resources and returns the provider onto
     * them.
     *
     * **Call this before `$server->start()`.** Everything below depends on that
     * and cannot check it: after the fork the calls all still succeed, and the
     * only symptom is workers that disagree about what they have cached.
     *
     * It builds its own logger rather than taking one, because it runs before
     * the object graph exists and the alternative would be threading a
     * collaborator through the composition root for one line.
     *
     * @param  Server  $server  The server the sweeper process is added to.
     * @param  CacheConfig  $cache  How large the cache is and how often it is
     *                              swept.
     * @param  LogConfig  $log  The level the sweeper logs at.
     * @return IOpenSwooleExtensionProvider Handed down the register chain at
     *                                      `WorkerStart`, and consumed by
     *                                      {@see Interno\InfraProvider}.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function attach(Server $server, CacheConfig $cache, LogConfig $log): IOpenSwooleExtensionProvider
    {
        $logger = MonologFactory::create(level: $log->level);

        OpenSwooleCacheProcessAdapter::attach($server, $cache, $logger);

        return new OpenSwooleExtensionProvider($cache, $logger);
    }
}
