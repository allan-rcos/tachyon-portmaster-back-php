<?php

/**
 * OpenSwoole Cache Process Adapter.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Cache\Interno;

use Domain\Security\IIndexHasher;
use Infra\Cache\CacheProcessDatabaseConfig;
use Infra\Cache\ICacheProcessDatabase;
use Infra\Cache\IOpenSwooleCacheProcessAdapter;
use Infra\Config\CacheConfig;
use Infra\Config\CacheLimits;
use Infra\Logging\ILogger;
use OpenSwoole\Process;
use OpenSwoole\Server;
use OpenSwoole\Table;
use RuntimeException;

/**
 * Allocates the shared cache before the fork, and hands out slices of it after.
 *
 * The class has two halves that never run at the same time, and the split is the
 * whole design:
 *
 *  - **{@see attach()}, static, in the global context.** Builds the two tables
 *    and adds the sweeper process, both before `$server->start()`. An
 *    `OpenSwoole\Table` created here is inherited by every worker the fork
 *    produces, so all of them address the same memory. This is the "revisit if"
 *    ADR 0002 and ADR 0010 both left open — the reason the registries and the
 *    read cache were in MariaDB was that the object graph is built inside
 *    `WorkerStart`, after the fork, where a table would be one table per worker.
 *  - **{@see database()}, per instance, inside a worker.** Finds the tables
 *    already there and wraps a slice of them.
 *
 * The tables are kept in statics because that is the only channel between the
 * two halves: `attach()` runs in the master, `database()` runs in a process the
 * master forked, and a forked child inherits its parent's statics. Nothing else
 * in this codebase communicates that way, which is why it is confined to this
 * one class.
 *
 * @see IOpenSwooleCacheProcessAdapter The contract this implements.
 * @see \Infra\OpenSwooleExtension The only caller of {@see attach()}.
 * @see TableCacheProcessDatabase What {@see database()} returns.
 * @see CacheProcessSweeper What the added process runs.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why the cache lives here.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class OpenSwooleCacheProcessAdapter implements IOpenSwooleCacheProcessAdapter
{
    /**
     * @var Table|null The logical key and expiry of every entry, keyed by
     *                 digest. Allocated by {@see attach()}, inherited by every
     *                 worker.
     */
    private static ?Table $index = null;

    /**
     * @var Table|null The payloads, keyed by the same digest. Split from
     *                 {@see $index} so nothing that iterates has to copy them;
     *                 see {@see TableCacheProcessDatabase}.
     */
    private static ?Table $store = null;

    /**
     * @var int Declared width of `store.payload`, remembered because a table
     *          cannot be asked how wide its columns are.
     */
    private static int $payloadBytes = 0;

    /**
     * @param  CacheConfig  $config  The sizing the tables were created with.
     * @param  ILogger  $logger  Passed on to every database built here.
     * @param  IIndexHasher  $hasher  Passed on likewise; it is what turns a cache
     *                                key into a table key.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly CacheConfig $config,
        private readonly ILogger $logger,
        private readonly IIndexHasher $hasher,
    ) {
    }

    /**
     * Allocates the shared tables and attaches the sweeper to the server.
     *
     * **Must be called before `$server->start()`**, from the global context.
     * Called after the fork it would allocate one table per worker, and an
     * invalidation on worker 2 would be invisible to worker 3 — the bug ADR 0002
     * records.
     *
     * Idempotent: a second call with the tables already allocated does nothing,
     * so a test harness that boots twice in one process does not get two caches.
     *
     * @param  Server  $server  The server to add the sweeper process to.
     * @param  CacheConfig  $config  How large the tables are and how often they
     *                               are swept.
     * @param  ILogger  $logger  Handed to the sweeper.
     *
     * @throws RuntimeException When the tables cannot be allocated, which means
     *                          the machine would not give up the memory. There is
     *                          no degraded mode to fall back to here: the server
     *                          has not started, and booting without a cache the
     *                          repositories are about to be wired to would fail
     *                          later and less clearly.
     *
     * @copyright 2026 Tachyon
     */
    public static function attach(Server $server, CacheConfig $config, ILogger $logger): void
    {
        if (self::$index instanceof Table && self::$store instanceof Table) {
            return;
        }

        $index = new Table($config->entries);
        $index->column('logical', Table::TYPE_STRING, CacheLimits::LOGICAL_KEY_MAX_BYTES);
        $index->column('expires', Table::TYPE_INT, 8);

        $store = new Table($config->entries);
        $store->column('payload', Table::TYPE_STRING, $config->payloadBytes);

        if (!$index->create() || !$store->create()) {
            throw new RuntimeException(
                'The cache tables could not be allocated. '.
                "Asked for $config->entries entries of $config->payloadBytes bytes.",
            );
        }

        self::$index = $index;
        self::$store = $store;
        self::$payloadBytes = $config->payloadBytes;

        // enableCoroutine, the fourth argument, is what gives the process an
        // event loop; without it Timer::tick() has nothing to run on and the
        // process exits as soon as the callback returns.
        $server->addProcess(new Process(
            new CacheProcessSweeper($index, $store, $config, $logger),
            false,
            0,
            true,
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  CacheProcessDatabaseConfig  $config  Which slice, and how long its
     *                                              entries live by default.
     * @return ICacheProcessDatabase A view onto the shared tables; nothing is
     *                               allocated.
     *
     * @throws RuntimeException When {@see attach()} has not run. That is a wiring
     *                          mistake rather than a runtime condition — it means
     *                          the extension was not registered before
     *                          `$server->start()` — so it fails loudly at
     *                          `WorkerStart` instead of degrading into a cache
     *                          that silently never hits.
     *
     * @copyright 2026 Tachyon
     */
    public function database(CacheProcessDatabaseConfig $config): ICacheProcessDatabase
    {
        if (!self::$index instanceof Table || !self::$store instanceof Table) {
            throw new RuntimeException(
                'The cache tables were never allocated: OpenSwooleExtension::attach() '.
                'has to run in the global context, before $server->start().',
            );
        }

        return new TableCacheProcessDatabase(
            self::$index,
            self::$store,
            self::$payloadBytes > 0 ? self::$payloadBytes : $this->config->payloadBytes,
            $config,
            $this->hasher,
            $this->logger,
        );
    }
}
