<?php

declare(strict_types=1);

namespace Domain\Interno;

use Domain\Config\DomainConfig;
use Domain\IDomainProvider;
use Domain\ID\IDatabaseIdGenerator;
use Domain\ID\IRandomIdGenerator;
use Domain\ID\ISequentialIdGenerator;
use Domain\ID\Interno\NanoIdGenerator;
use Domain\ID\Interno\SnowflakeIdGenerator;
use Domain\ID\Interno\UlidGenerator;
use Domain\Security\IIndexHasher;
use Domain\Security\ISecureHasher;
use Domain\Security\Interno\Argon2Hasher;
use Domain\Security\Interno\XxHasher;
use Domain\TableModules\IAuthTM;
use Domain\TableModules\IContainerTM;
use Domain\TableModules\IManifestTM;
use Domain\TableModules\IProductTM;
use Domain\TableModules\IRoleTM;
use Domain\TableModules\IMarkerGroupTM;
use Domain\TableModules\IMarkerTM;
use Domain\TableModules\IPermissionTM;
use Domain\TableModules\ITelemetryEventTM;
use Domain\TableModules\IUserTM;
use Domain\TableModules\Interno\AuthTM;
use Domain\TableModules\Interno\ContainerTM;
use Domain\TableModules\Interno\ManifestTM;
use Domain\TableModules\Interno\ProductTM;
use Domain\TableModules\Interno\RoleTM;
use Domain\TableModules\Interno\MarkerGroupTM;
use Domain\TableModules\Interno\MarkerTM;
use Domain\TableModules\Interno\PermissionTM;
use Domain\TableModules\Interno\TelemetryEventTM;
use Domain\TableModules\Interno\UserTM;
use RuntimeException;
use Shared\Exceptions\LeafContext;

/**
 * Hand-wired domain provider. Each id generator is a per-worker singleton — for
 * the Snowflake it is a correctness requirement (its sequence counter must not
 * be shared across instances), for the other two just reuse. TableModules are
 * cheap and memoized. Nothing here is exported — only the
 * {@see IDomainProvider} contract is.
 */
final class DomainProvider implements IDomainProvider
{
    private ?IDatabaseIdGenerator $databaseIdGenerator = null;
    private ?ISequentialIdGenerator $sequentialIdGenerator = null;
    private ?IRandomIdGenerator $randomIdGenerator = null;
    private ?IUserTM $userTM = null;
    private ?IRoleTM $roleTM = null;
    private ?IAuthTM $authTM = null;
    private ?IProductTM $productTM = null;
    private ?IContainerTM $containerTM = null;
    private ?IManifestTM $manifestTM = null;
    private ?IPermissionTM $permissionTM = null;
    private ?IMarkerGroupTM $markerGroupTM = null;
    private ?IMarkerTM $markerTM = null;
    private ?ITelemetryEventTM $telemetryEventTM = null;

    private ?ISecureHasher $secureHasher = null;
    private ?IIndexHasher $indexHasher = null;

    public function __construct(
        private readonly DomainConfig $config,
        private readonly int $serverId,
    ) {
    }

    /**
     * The password hasher, private on purpose: only this layer's TableModules
     * hash or verify credentials, so exposing it would widen the surface for no
     * caller that exists.
     */
    private function secureHasher(): ISecureHasher
    {
        return $this->secureHasher ??= new Argon2Hasher();
    }

    /**
     * The index hasher, public because the value it keys is not a domain
     * secret: the application layer hands it an already-unguessable string and
     * gets back the key it will look that string up by. Nothing is protected
     * here, so nothing needs hiding.
     */
    public function indexHasher(): IIndexHasher
    {
        return $this->indexHasher ??= new XxHasher();
    }

    public function databaseIdGenerator(): IDatabaseIdGenerator
    {
        if ($this->databaseIdGenerator === null) {
            $generator = SnowflakeIdGenerator::create(
                $this->config->snowflakeClusterId,
                $this->serverId,
                $this->config->snowflakeEpoch,
            );
            if ($generator instanceof LeafContext) {
                throw new RuntimeException('Invalid snowflake configuration: '.$generator->message);
            }
            $this->databaseIdGenerator = $generator;
        }

        return $this->databaseIdGenerator;
    }

    public function sequentialIdGenerator(): ISequentialIdGenerator
    {
        return $this->sequentialIdGenerator ??= new UlidGenerator();
    }

    public function randomIdGenerator(): IRandomIdGenerator
    {
        return $this->randomIdGenerator ??= new NanoIdGenerator();
    }

    public function userTM(): IUserTM
    {
        return $this->userTM ??= new UserTM($this->databaseIdGenerator(), $this->secureHasher());
    }

    public function roleTM(): IRoleTM
    {
        return $this->roleTM ??= new RoleTM($this->databaseIdGenerator());
    }

    public function authTM(): IAuthTM
    {
        return $this->authTM ??= new AuthTM($this->secureHasher());
    }

    public function productTM(): IProductTM
    {
        return $this->productTM ??= new ProductTM($this->databaseIdGenerator());
    }

    public function containerTM(): IContainerTM
    {
        return $this->containerTM ??= new ContainerTM($this->databaseIdGenerator());
    }

    public function manifestTM(): IManifestTM
    {
        return $this->manifestTM ??= new ManifestTM();
    }

    public function permissionTM(): IPermissionTM
    {
        return $this->permissionTM ??= new PermissionTM();
    }

    public function markerGroupTM(): IMarkerGroupTM
    {
        return $this->markerGroupTM ??= new MarkerGroupTM();
    }

    /**
     * The one table module that takes the index hasher: it is where a plain
     * value becomes the key it is stored under.
     */
    public function markerTM(): IMarkerTM
    {
        return $this->markerTM ??= new MarkerTM($this->indexHasher());
    }

    public function telemetryEventTM(): ITelemetryEventTM
    {
        return $this->telemetryEventTM ??= new TelemetryEventTM();
    }
}
