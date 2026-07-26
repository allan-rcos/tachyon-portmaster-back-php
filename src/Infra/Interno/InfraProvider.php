<?php

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
use Infra\Repository\ITelemetryEventRepository;
use Infra\Repository\IUserRepository;
use Infra\Repository\Interno\SqlContainerRepository;
use Infra\Repository\Interno\MarkerGroupRegistry;
use Infra\Repository\Interno\SqlManifestRepository;
use Infra\Repository\Interno\SqlMarkerRepository;
use Infra\Repository\Interno\PermissionRegistry;
use Infra\Repository\Interno\SqlProductRepository;
use Infra\Repository\Interno\SqlRoleRepository;
use Infra\Repository\Interno\SqlUserRepository;
use Infra\Repository\Interno\TelemetryEventRegistry;

/**
 * Hand-wired infrastructure provider. The pool, transaction session, logger,
 * hasher, token service and repositories are lazy singletons — matching the
 * previous container's shared lifetime — so every consumer participates in the
 * same connection pool and per-request unit of work.
 */
final class InfraProvider implements IInfraProvider
{
    private ?ILogger $logger = null;
    private ?IPDOPool $pool = null;
    private ?IUnitOfWork $unitOfWork = null;
    private ?PdoTransactionSession $pdoTransactionSession = null;
    private ?IUserRepository $userRepository = null;
    private ?IRoleRepository $roleRepository = null;
    private ?IProductRepository $productRepository = null;
    private ?IContainerRepository $containerRepository = null;
    private ?IManifestRepository $manifestRepository = null;
    private ?IPermissionRepository $permissionRepository = null;
    private ?IMarkerGroupRepository $markerGroupRepository = null;
    private ?IMarkerRepository $markerRepository = null;
    private ?ITelemetryEventRepository $telemetryEventRepository = null;
    private ?IQueryRepository $queryRepository = null;

    public function __construct(
        private readonly DatabaseConfig $database,
        private readonly LogConfig $log,
    ) {
    }

    public function logger(): ILogger
    {
        return $this->logger ??= MonologFactory::create(level: $this->log->level);
    }

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
     */
    private function pdoTransaction(): PdoTransactionSession
    {
        return $this->pdoTransactionSession ??= new PdoTransactionSession($this->pool(), $this->logger());
    }

    public function userRepository(): IUserRepository
    {
        return $this->userRepository ??= new SqlUserRepository($this->logger(), $this->pdoTransaction());
    }

    public function roleRepository(): IRoleRepository
    {
        return $this->roleRepository ??= new SqlRoleRepository($this->logger(), $this->pdoTransaction());
    }

    public function productRepository(): IProductRepository
    {
        return $this->productRepository ??= new SqlProductRepository($this->logger(), $this->pdoTransaction());
    }

    public function containerRepository(): IContainerRepository
    {
        return $this->containerRepository ??= new SqlContainerRepository($this->logger(), $this->pdoTransaction());
    }

    public function manifestRepository(): IManifestRepository
    {
        return $this->manifestRepository ??= new SqlManifestRepository($this->logger(), $this->pdoTransaction());
    }


    /**
     * The metadata registries lease from the pool directly rather than joining a
     * transaction: they are written at boot, before any request exists, so there
     * is no boundary open for them to enlist in.
     */
    public function permissionRepository(): IPermissionRepository
    {
        return $this->permissionRepository ??= new PermissionRegistry($this->pool(), $this->logger());
    }

    public function markerGroupRepository(): IMarkerGroupRepository
    {
        return $this->markerGroupRepository ??= new MarkerGroupRegistry($this->pool(), $this->logger());
    }

    /**
     * Markers, unlike the registries above, are written on the request path, so
     * this one joins the caller's transaction like any other repository.
     */
    public function markerRepository(): IMarkerRepository
    {
        return $this->markerRepository ??= new SqlMarkerRepository(
            $this->logger(),
            $this->pdoTransaction(),
            $this->markerGroupRepository(),
        );
    }

    public function telemetryEventRepository(): ITelemetryEventRepository
    {
        return $this->telemetryEventRepository ??= new TelemetryEventRegistry($this->pool(), $this->logger());
    }

    public function queryRepository(): IQueryRepository
    {
        return $this->queryRepository ??= new SqlQueryRepository($this->pool(), $this->logger());
    }
}
