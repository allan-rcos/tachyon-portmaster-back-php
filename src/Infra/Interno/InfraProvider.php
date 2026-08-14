<?php

/**
 * Infra Provider.
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

use Infra\Config\DatabaseConfig;
use Infra\Config\LogConfig;
use Infra\Database\IUnitOfWork;
use Infra\Database\Interno\PdoTransactionSession;
use Infra\Database\Interno\UnitOfWorkIterator;
use Infra\Database\Pool\IPDOPool;
use Infra\Database\Pool\Interno\OpenSwoolePDOClientPool;
use Infra\Database\Pool\Interno\PDOConfigFactory;
use Infra\IInfraProvider;
use Infra\Logging\ILogger;
use Infra\Logging\MonologFactory;
use Infra\Query\IQueryRepository;
use Infra\Query\Interno\SqlQueryRepository;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Infra\Repository\IMarkerGroupRepository;
use Infra\Repository\IMarkerRepository;
use Infra\Repository\IPermissionRepository;
use Infra\Repository\IProductRepository;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\Interno\SqlContainerRepository;
use Infra\Repository\Interno\MarkerGroupRegistry;
use Infra\Repository\Interno\SqlManifestRepository;
use Infra\Repository\Interno\SqlMarkerRepository;
use Infra\Repository\Interno\PermissionRegistry;
use Infra\Repository\Interno\SqlProductRepository;
use Infra\Repository\Interno\SqlRoleRepository;
use Infra\Repository\Interno\SqlUserRepository;
use Infra\Repository\Interno\SqlViewCacheRepository;

/**
 * Hand-wired infrastructure provider. The pool, transaction session, logger,
 * hasher, token service and repositories are lazy singletons — matching the
 * previous container's shared lifetime — so every consumer participates in the
 * same connection pool and per-request unit of work.
 *
 * The sharing is the point, not an optimisation: every repository must hold the
 * *same* {@see PdoTransactionSession}, or a use case's boundary would not cover
 * the writes made through them.
 *
 * Nothing in this class is exported; only {@see IInfraProvider} is — and it is
 * deliberately narrower, omitting {@see pdoTransaction()}.
 *
 * @see IInfraProvider The contract.
 * @see \Infra\InfraRegister What constructs one.
 * @see \Domain\Interno\DomainProvider The same pattern, a layer down.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class InfraProvider implements IInfraProvider
{
    /**
     * @var ?ILogger Memoized root logger; null until first use.
     */
    private ?ILogger $logger = null;

    /**
     * @var ?IPDOPool Memoized connection pool; null until first use, which is
     *                when the first connection is opened.
     */
    private ?IPDOPool $pool = null;

    /**
     * @var ?IUnitOfWork Memoized composite boundary; null until first use.
     */
    private ?IUnitOfWork $unitOfWork = null;

    /**
     * @var ?PdoTransactionSession Memoized session backing both halves of the
     *                             split; null until first use.
     */
    private ?PdoTransactionSession $pdoTransactionSession = null;

    /**
     * @var ?IUserRepository Memoized repository; null until first use.
     */
    private ?IUserRepository $userRepository = null;

    /**
     * @var ?IRoleRepository Memoized repository; null until first use.
     */
    private ?IRoleRepository $roleRepository = null;

    /**
     * @var ?IProductRepository Memoized repository; null until first use.
     */
    private ?IProductRepository $productRepository = null;

    /**
     * @var ?IContainerRepository Memoized repository; null until first use.
     */
    private ?IContainerRepository $containerRepository = null;

    /**
     * @var ?IManifestRepository Memoized repository; null until first use.
     */
    private ?IManifestRepository $manifestRepository = null;

    /**
     * @var ?IPermissionRepository Memoized registry; null until first use.
     */
    private ?IPermissionRepository $permissionRepository = null;

    /**
     * @var ?IMarkerGroupRepository Memoized registry; null until first use.
     */
    private ?IMarkerGroupRepository $markerGroupRepository = null;

    /**
     * @var ?IMarkerRepository Memoized repository; null until first use.
     */
    private ?IMarkerRepository $markerRepository = null;

    /**
     * @var ?IQueryRepository Memoized read-side runner; null until first use.
     */
    private ?IQueryRepository $queryRepository = null;

    /**
     * @var ?IViewCacheRepository Lazy singleton; the read cache every list use
     *                            case consults and every write drops from.
     */
    private ?IViewCacheRepository $viewCacheRepository = null;

    /**
     * Stores the config and builds nothing — every factory is lazy, so
     * constructing a provider never touches the database.
     *
     * @param  DatabaseConfig  $database  Connection and pool settings.
     * @param  LogConfig  $log  The level to log at.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly DatabaseConfig $database,
        private readonly LogConfig $log,
    ) {
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function logger(): ILogger
    {
        return $this->logger ??= MonologFactory::create(level: $this->log->level);
    }

    /**
     * The pool, opened on first use.
     *
     * This is where the database is first contacted, so a bad
     * {@see DatabaseConfig} surfaces here rather than at bootstrap.
     *
     * @return IPDOPool Memoized, so every consumer shares one pool.
     *
     * @copyright 2026 Tachyon
     */
    public function pool(): IPDOPool
    {
        return $this->pool ??= new OpenSwoolePDOClientPool(
            config: PDOConfigFactory::mysql(
                host: $this->database->host,
                port: $this->database->port,
                dbname: $this->database->name,
                username: $this->database->user,
                password: $this->database->password,
                charset: $this->database->charset,
                sslMode: $this->database->sslMode,
                sslCa: $this->database->sslCa,
                sslVerifyCn: $this->database->sslVerifyCn,
            ),
            maxPoolSize: $this->database->poolSize,
            logger: $this->logger(),
            getTimeout: $this->database->poolTimeout,
        );
    }

    /**
     * The boundary use cases open. Today the database is its only participant,
     * so the composite wraps exactly one — which is the point: adding a second
     * participant later changes this line and nothing above it.
     *
     * @return IUnitOfWork The boundary half of the same session the repositories
     *                     are given the connection half of.
     *
     * @copyright 2026 Tachyon
     */
    public function unitOfWork(): IUnitOfWork
    {
        return $this->unitOfWork ??= new UnitOfWorkIterator($this->pdoTransaction());
    }

    /**
     * The connection side of the transaction session, given only to the
     * repositories built here.
     *
     * Private on purpose, and absent from {@see IInfraProvider}: this is what
     * keeps `getTransaction()` out of the application layer's reach. It returns
     * the same {@see PdoTransactionSession} instance that backs
     * {@see unitOfWork()}, so a repository is enlisted in the boundary its
     * caller opened.
     *
     * @return PdoTransactionSession The concrete session, not an interface —
     *                               nothing outside this class receives it, so
     *                               there is nothing to abstract from.
     *
     * @copyright 2026 Tachyon
     */
    private function pdoTransaction(): PdoTransactionSession
    {
        return $this->pdoTransactionSession ??= new PdoTransactionSession($this->pool(), $this->logger());
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function userRepository(): IUserRepository
    {
        return $this->userRepository ??= new SqlUserRepository($this->logger(), $this->pdoTransaction());
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function roleRepository(): IRoleRepository
    {
        return $this->roleRepository ??= new SqlRoleRepository($this->logger(), $this->pdoTransaction());
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function productRepository(): IProductRepository
    {
        return $this->productRepository ??= new SqlProductRepository($this->logger(), $this->pdoTransaction());
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function containerRepository(): IContainerRepository
    {
        return $this->containerRepository ??= new SqlContainerRepository($this->logger(), $this->pdoTransaction());
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function manifestRepository(): IManifestRepository
    {
        return $this->manifestRepository ??= new SqlManifestRepository($this->logger(), $this->pdoTransaction());
    }


    /**
     * The metadata registries lease from the pool directly rather than joining a
     * transaction: they are written at boot, before any request exists, so there
     * is no boundary open for them to enlist in.
     *
     * @return IPermissionRepository Memoized; takes the pool, not the session.
     *
     * @copyright 2026 Tachyon
     */
    public function permissionRepository(): IPermissionRepository
    {
        return $this->permissionRepository ??= new PermissionRegistry($this->pool(), $this->logger());
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function markerGroupRepository(): IMarkerGroupRepository
    {
        return $this->markerGroupRepository ??= new MarkerGroupRegistry($this->pool(), $this->logger());
    }

    /**
     * Markers, unlike the registries above, are written on the request path, so
     * this one joins the caller's transaction like any other repository.
     *
     * The only repository here with a third dependency: it needs the group
     * registry to resolve a slug into the index its rows store.
     *
     * @return IMarkerRepository Memoized; takes the session *and* the group
     *                           registry.
     *
     * @copyright 2026 Tachyon
     */
    public function markerRepository(): IMarkerRepository
    {
        return $this->markerRepository ??= new SqlMarkerRepository(
            $this->logger(),
            $this->pdoTransaction(),
            $this->markerGroupRepository(),
        );
    }

    /**
     * The read-side runner, which leases from the pool rather than joining a
     * transaction — read endpoints open no boundary at all.
     *
     * @return IQueryRepository Memoized; takes the pool, not the session.
     *
     * @copyright 2026 Tachyon
     */
    public function queryRepository(): IQueryRepository
    {
        return $this->queryRepository ??= new SqlQueryRepository($this->pool(), $this->logger());
    }

    /**
     * The read cache, which leases from the pool for the same reason the runner
     * above does — and additionally because invalidation runs *after* the commit
     * it follows, so there is no boundary left to join.
     *
     * Note what this is not: it does not wrap {@see queryRepository()}. The use
     * cases consult it themselves, because the group a write drops is an
     * application decision and hiding the read half of that policy behind the
     * runner would split one decision across two layers. It is also what keeps
     * this the single line to change when the entries should live in Redis
     * instead of an `ENGINE=MEMORY` table.
     *
     * @return IViewCacheRepository Memoized; takes the pool, not the session.
     *
     * @copyright 2026 Tachyon
     */
    public function viewCacheRepository(): IViewCacheRepository
    {
        return $this->viewCacheRepository ??= new SqlViewCacheRepository($this->pool(), $this->logger());
    }
}
