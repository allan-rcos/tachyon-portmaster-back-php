<?php

/**
 * OpenSwoole Extension Provider.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Interno;

use Domain\Security\IIndexHasher;
use Infra\Cache\Interno\OpenSwooleCacheProcessAdapter;
use Infra\Cache\IOpenSwooleCacheProcessAdapter;
use Infra\Config\CacheConfig;
use Infra\IOpenSwooleExtensionProvider;
use Infra\Logging\ILogger;

/**
 * {@see IOpenSwooleExtensionProvider} over the resources the server allocated
 * before it forked.
 *
 * Unlike {@see InfraProvider} it memoizes nothing: what it hands back is a
 * *handle*, and the memory that handle addresses was allocated once, in the
 * master, by {@see OpenSwooleCacheProcessAdapter::attach()}. Building a second
 * one costs a constructor call.
 *
 * Constructed in the global context by {@see \Infra\OpenSwooleExtension} and then
 * inherited by the fork, so every worker starts from the same instance and
 * memoizes its own handles on top of it.
 *
 * @see IOpenSwooleExtensionProvider The contract this implements.
 * @uses CacheConfig The sizing the tables were allocated with.
 * @uses ILogger Handed to everything built here.
 * @see \Infra\OpenSwooleExtension What constructs it.
 * @see InfraProvider The per-worker counterpart, and the one consumer.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class OpenSwooleExtensionProvider implements IOpenSwooleExtensionProvider
{
    /**
     * @param  CacheConfig  $cache  The sizing the tables were allocated with,
     *                              carried so a database can tell a payload that
     *                              does not fit from one that does.
     * @param  ILogger  $logger  Passed to everything built here, which rebinds it
     *                           to its own channel.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly CacheConfig $cache,
        private readonly ILogger $logger,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * @param  IIndexHasher  $hasher  Passed straight to the adapter.
     * @return IOpenSwooleCacheProcessAdapter A fresh handle; nothing is allocated.
     *
     * @copyright 2026 Tachyon
     */
    public function cacheProcessAdapter(IIndexHasher $hasher): IOpenSwooleCacheProcessAdapter
    {
        return new OpenSwooleCacheProcessAdapter($this->cache, $this->logger, $hasher);
    }
}
