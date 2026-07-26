<?php

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
use Domain\TableModules\ITelemetryEventTM;
use Domain\TableModules\IUserTM;

/**
 * Factory surface of the domain layer.
 *
 * Every factory returns a service **interface**; the concrete implementations
 * stay private to the layer (see {@see \Domain\Interno\DomainProvider}). Built by
 * {@see DomainRegister::execute}, which receives the domain config and
 * the runtime server id. Of the two hasher flavours only
 * {@see IIndexHasher} is exposed; the password hasher
 * ({@see \Domain\Security\ISecureHasher}) is built internally and never leaves
 * the layer, because nothing outside it has a password to hash.
 *
 * TODO: rethink how providers are built. Every factory below memoizes its result
 * into a property, so the provider holds the whole object graph alive for the
 * worker's lifetime — convenient, but it makes lifetime implicit and untestable,
 * and there is no way to scope an instance to a request. Worth replacing with an
 * explicit lifetime mechanism before the graph grows further.
 */
interface IDomainProvider
{
    /** Ids destined for a database primary key (Snowflake). */
    public function databaseIdGenerator(): IDatabaseIdGenerator;

    /** Chronologically sortable ids for logs/traces/correlation (ULID). */
    public function sequentialIdGenerator(): ISequentialIdGenerator;

    /** Unguessable ids for exact-match lookups and opaque tokens (NanoID). */
    public function randomIdGenerator(): IRandomIdGenerator;

    /**
     * Turns an already-unguessable string into the key it is stored under.
     * Never for passwords — that is {@see \Domain\Security\ISecureHasher}, which
     * this layer keeps to itself.
     */
    public function indexHasher(): IIndexHasher;

    public function userTM(): IUserTM;

    public function roleTM(): IRoleTM;

    public function authTM(): IAuthTM;

    public function productTM(): IProductTM;

    public function containerTM(): IContainerTM;

    public function manifestTM(): IManifestTM;

    public function permissionTM(): IPermissionTM;

    public function markerGroupTM(): IMarkerGroupTM;

    public function markerTM(): IMarkerTM;

    public function telemetryEventTM(): ITelemetryEventTM;
}
