<?php

/**
 * Domain Provider Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain;

use Domain\ID\IDatabaseIdGenerator;
use Domain\ID\IRandomIdGenerator;
use Domain\ID\ISequentialIdGenerator;
use Domain\Security\IIndexHasher;
use Domain\TableModules\IAuthTM;
use Domain\TableModules\IContainerTM;
use Domain\TableModules\IManifestTM;
use Domain\TableModules\IMarkerGroupTM;
use Domain\TableModules\IMarkerTM;
use Domain\TableModules\IPermissionTM;
use Domain\TableModules\IProductTM;
use Domain\TableModules\IRoleTM;
use Domain\TableModules\IUserTM;

/**
 * Everything the domain layer offers the layers above it.
 *
 * Every factory returns an interface; the implementations stay private to the
 * layer. Built once per worker by {@see DomainRegister::execute()}.
 *
 * Of the two hasher flavours only {@see IIndexHasher} is exposed. The password
 * hasher ({@see \Domain\Security\ISecureHasher}) is built internally and never
 * leaves the layer, because nothing outside it has a password to hash.
 *
 * @see \Domain\Interno\DomainProvider The implementation.
 * @see docs/adr/0006-layered-providers-per-feature.md Why there is no container.
 *
 * @todo Rethink how providers are built. Every factory below memoizes its result
 *       into a property, so the provider holds the whole object graph alive for
 *       the worker's lifetime — convenient, but it makes lifetime implicit and
 *       untestable, and there is no way to scope an instance to a request.
 *       Worth replacing with an explicit lifetime mechanism before the graph
 *       grows further.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IDomainProvider
{
    /**
     * Ids destined for a database primary key (Snowflake, time-ordered).
     *
     * @return IDatabaseIdGenerator The per-worker generator.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function databaseIdGenerator(): IDatabaseIdGenerator;

    /**
     * Chronologically sortable ids for logs, traces and correlation (ULID).
     *
     * @return ISequentialIdGenerator The per-worker generator.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function sequentialIdGenerator(): ISequentialIdGenerator;

    /**
     * Unguessable ids for exact-match lookups and opaque tokens (NanoID).
     *
     * @return IRandomIdGenerator The per-worker generator.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function randomIdGenerator(): IRandomIdGenerator;

    /**
     * Turns an already-unguessable string into the key it is stored under.
     *
     * Never for passwords — that is {@see \Domain\Security\ISecureHasher}, which
     * this layer keeps to itself.
     *
     * @return IIndexHasher The per-worker hasher.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function indexHasher(): IIndexHasher;

    /**
     * Rules for users, and the only place a password is hashed.
     *
     * @return IUserTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function userTM(): IUserTM;

    /**
     * Rules for roles and their permission sets.
     *
     * @return IRoleTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function roleTM(): IRoleTM;

    /**
     * The credential check.
     *
     * @return IAuthTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function authTM(): IAuthTM;

    /**
     * Rules for products.
     *
     * @return IProductTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function productTM(): IProductTM;

    /**
     * Rules for containers, and every status transition.
     *
     * @return IContainerTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function containerTM(): IContainerTM;

    /**
     * Loading and unloading arithmetic.
     *
     * @return IManifestTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function manifestTM(): IManifestTM;

    /**
     * Rules for permission slugs.
     *
     * @return IPermissionTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function permissionTM(): IPermissionTM;

    /**
     * Rules for marker-group slugs.
     *
     * @return IMarkerGroupTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function markerGroupTM(): IMarkerGroupTM;

    /**
     * Hashes a value into the marker that stands in for it.
     *
     * @return IMarkerTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function markerTM(): IMarkerTM;
}
