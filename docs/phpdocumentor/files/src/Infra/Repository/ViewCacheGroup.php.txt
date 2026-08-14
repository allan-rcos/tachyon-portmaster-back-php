<?php

/**
 * View Cache Group Enum.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Repository;

/**
 * The slice of the read cache a write drops.
 *
 * A group maps to a resource whose reads and writes move together, never to a
 * table. That is what lets a product write discard the `Product` group without
 * knowing which listings exist, so adding a listing never means hunting for the
 * writes that ought to invalidate it.
 *
 * **A write drops only its own group**, so a resource goes stale in another
 * group's views for at most
 * {@see \Infra\Config\CacheLimits::TTL_SECONDS}. Several writes therefore drop
 * nothing at all — the session use cases, the account ones, and `SetupUseCase`.
 * Which, and why each is safe, is in the ADR.
 *
 * An enum where the Rust implementation uses a private `const` per service: one
 * service owns every operation on a resource there, while here the use cases are
 * one per operation, so `'product'` would be written out in four classes and a
 * typo in any of them would be a cache that silently never invalidates.
 *
 * @see IViewCacheRepository What takes one of these.
 * @see docs/adr/0010-read-cache-in-a-memory-table.md Why a write drops only its own group, and which drop nothing.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum ViewCacheGroup: string
{
    /**
     * Container listings, summaries included.
     *
     * Both listings share a group because both are dropped by the same writes —
     * sealing a container changes what either would return.
     */
    case Container = 'container';

    /**
     * The occupancy and throughput panel.
     *
     * Nothing invalidates this one. It is derived from containers and products,
     * and each of those drops its own group only, so the panel goes stale for at
     * most one TTL — on purpose.
     */
    case Metrics = 'metrics';

    /**
     * Product listings.
     */
    case Product = 'product';

    /**
     * Role listings.
     */
    case Role = 'role';

    /**
     * User listings.
     */
    case User = 'user';
}
