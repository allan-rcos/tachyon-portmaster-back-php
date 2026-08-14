<?php

/**
 * Infra Provider Contract.
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

use Infra\Database\IUnitOfWork;
use Infra\Database\Pool\IPDOPool;
use Infra\Logging\ILogger;
use Infra\Query\IQueryRepository;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Infra\Repository\IMarkerGroupRepository;
use Infra\Repository\IMarkerRepository;
use Infra\Repository\IPermissionRepository;
use Infra\Repository\IProductRepository;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;

/**
 * Factory surface of the infrastructure layer.
 *
 * Every factory returns a service **interface**; the concrete adapters stay
 * private (see {@see \Infra\Interno\InfraProvider}). Built by
 * {@see \Infra\InfraRegister::execute()} from the per-layer config VOs. The
 * shared resources ({@see IPDOPool}, {@see IUnitOfWork}, logger) are memoized so
 * every repository shares the same pool and per-request transaction session.
 *
 * Note what is **not** here: {@see \Infra\Database\IPdoTransaction}. The
 * connection side of the transaction session is private to the provider, handed
 * only to the repositories it builds — so no use case can reach a PDO, and no
 * repository can open or close a boundary.
 *
 * @see \Infra\Interno\InfraProvider The implementation.
 * @see \Infra\InfraRegister What builds one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IInfraProvider
{
    /**
     * The application logger, on its root channel.
     *
     * @return ILogger Callers rebrand it with {@see ILogger::withChannel()}.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function logger(): ILogger;

    /**
     * The connection pool, shared by everything in this layer.
     *
     * @return IPDOPool Exposed because the metadata registries and the query
     *                  runner borrow from it directly, having no request
     *                  boundary to enlist in.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function pool(): IPDOPool;

    /**
     * The composite boundary a use case opens, spanning every participant.
     *
     * @return IUnitOfWork The boundary half only — never the connection half.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function unitOfWork(): IUnitOfWork;

    /**
     * Persistence for users and their role assignments.
     *
     * @return IUserRepository Enlisted in the caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function userRepository(): IUserRepository;

    /**
     * Persistence for roles.
     *
     * @return IRoleRepository Enlisted in the caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function roleRepository(): IRoleRepository;

    /**
     * Persistence for products.
     *
     * @return IProductRepository Enlisted in the caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function productRepository(): IProductRepository;

    /**
     * Persistence for containers, without their cargo.
     *
     * @return IContainerRepository Enlisted in the caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function containerRepository(): IContainerRepository;

    /**
     * Persistence for what a container carries, and for its telemetry.
     *
     * @return IManifestRepository Enlisted in the caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function manifestRepository(): IManifestRepository;

    /**
     * The runtime catalogue of declared permissions.
     *
     * @return IPermissionRepository A registry, not a persistence repository; it
     *                               takes no part in the caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function permissionRepository(): IPermissionRepository;

    /**
     * The runtime catalogue of declared marker groups.
     *
     * @return IMarkerGroupRepository A registry; it takes no part in the
     *                                caller's boundary.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function markerGroupRepository(): IMarkerGroupRepository;

    /**
     * Storage for the expiring flags themselves.
     *
     * @return IMarkerRepository Joins the caller's boundary, though its table is
     *                           non-transactional — a rollback will not undo a
     *                           marker write.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function markerRepository(): IMarkerRepository;

    /**
     * The read side: runs a query and returns its view.
     *
     * @return IQueryRepository Leases its own connection, so reads open no
     *                          transaction.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function queryRepository(): IQueryRepository;

    /**
     * The read cache: holds what a query returned, keyed by group and key.
     *
     * Beside {@see queryRepository()} rather than in front of it — the list
     * use cases consult it themselves, because the group a write drops is
     * theirs to name.
     *
     * @return IViewCacheRepository Leases its own connection, since
     *                              invalidation runs after the commit it
     *                              follows and has no boundary to join.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function viewCacheRepository(): IViewCacheRepository;
}
