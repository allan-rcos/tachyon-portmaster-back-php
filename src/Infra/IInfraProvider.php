<?php

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
use Infra\Repository\ITelemetryEventRepository;
use Infra\Repository\IUserRepository;

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
 */
interface IInfraProvider
{
    public function logger(): ILogger;

    public function pool(): IPDOPool;

    /**
     * The composite boundary a use case opens, spanning every participant.
     */
    public function unitOfWork(): IUnitOfWork;

    public function userRepository(): IUserRepository;

    public function roleRepository(): IRoleRepository;

    public function productRepository(): IProductRepository;

    public function containerRepository(): IContainerRepository;

    public function manifestRepository(): IManifestRepository;


    public function permissionRepository(): IPermissionRepository;

    public function markerGroupRepository(): IMarkerGroupRepository;

    public function markerRepository(): IMarkerRepository;

    public function telemetryEventRepository(): ITelemetryEventRepository;

    public function queryRepository(): IQueryRepository;
}
