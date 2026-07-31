<?php

/**
 * Domain Provider.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
use Domain\TableModules\IUserTM;
use Domain\TableModules\Interno\AuthTM;
use Domain\TableModules\Interno\ContainerTM;
use Domain\TableModules\Interno\ManifestTM;
use Domain\TableModules\Interno\ProductTM;
use Domain\TableModules\Interno\RoleTM;
use Domain\TableModules\Interno\MarkerGroupTM;
use Domain\TableModules\Interno\MarkerTM;
use Domain\TableModules\Interno\PermissionTM;
use Domain\TableModules\Interno\UserTM;
use RuntimeException;
use Shared\Exceptions\LeafContext;

/**
 * Hand-wired construction of everything in the domain layer.
 *
 * Every factory memoizes into a property, so one instance per worker is the
 * real lifetime of everything here. For the Snowflake generator that is a
 * **correctness requirement** — its sequence counter must not be shared across
 * instances — and for the rest it is reuse.
 *
 * Nothing in this class is exported; only {@see IDomainProvider} is.
 *
 * @see IDomainProvider The contract, and the note on how lifetimes work.
 * @see docs/adr/0006-layered-providers-per-feature.md Why the wiring is hand-written.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class DomainProvider implements IDomainProvider
{
    /**
     * @var ?IDatabaseIdGenerator Memoized Snowflake generator; null until first use.
     */
    private ?IDatabaseIdGenerator $databaseIdGenerator = null;

    /**
     * @var ?ISequentialIdGenerator Memoized ULID generator; null until first use.
     */
    private ?ISequentialIdGenerator $sequentialIdGenerator = null;

    /**
     * @var ?IRandomIdGenerator Memoized NanoID generator; null until first use.
     */
    private ?IRandomIdGenerator $randomIdGenerator = null;

    /**
     * @var ?IUserTM Memoized table module; null until first use.
     */
    private ?IUserTM $userTM = null;

    /**
     * @var ?IRoleTM Memoized table module; null until first use.
     */
    private ?IRoleTM $roleTM = null;

    /**
     * @var ?IAuthTM Memoized table module; null until first use.
     */
    private ?IAuthTM $authTM = null;

    /**
     * @var ?IProductTM Memoized table module; null until first use.
     */
    private ?IProductTM $productTM = null;

    /**
     * @var ?IContainerTM Memoized table module; null until first use.
     */
    private ?IContainerTM $containerTM = null;

    /**
     * @var ?IManifestTM Memoized table module; null until first use.
     */
    private ?IManifestTM $manifestTM = null;

    /**
     * @var ?IPermissionTM Memoized table module; null until first use.
     */
    private ?IPermissionTM $permissionTM = null;

    /**
     * @var ?IMarkerGroupTM Memoized table module; null until first use.
     */
    private ?IMarkerGroupTM $markerGroupTM = null;

    /**
     * @var ?IMarkerTM Memoized table module; null until first use.
     */
    private ?IMarkerTM $markerTM = null;

    /**
     * @var ?ISecureHasher Memoized argon2id hasher; null until first use. Never
     *                     leaves the layer.
     */
    private ?ISecureHasher $secureHasher = null;

    /**
     * @var ?IIndexHasher Memoized xxHash hasher; null until first use.
     */
    private ?IIndexHasher $indexHasher = null;

    /**
     * @param  DomainConfig  $config  Snowflake epoch and cluster id.
     * @param  int  $serverId  The OpenSwoole worker id, used as the Snowflake
     *                         machine id.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly DomainConfig $config,
        private readonly int $serverId,
    ) {
    }

    /**
     * The password hasher.
     *
     * Private on purpose: only this layer's table modules hash or verify
     * credentials, so exposing it would widen the surface for no caller that
     * exists.
     *
     * @return ISecureHasher The memoized argon2id hasher.
     *
     * @copyright 2026 Tachyon
     */
    private function secureHasher(): ISecureHasher
    {
        return $this->secureHasher ??= new Argon2Hasher();
    }

    /**
     * The index hasher.
     *
     * Public because the value it keys is not a domain secret: the application
     * layer hands it an already-unguessable string and gets back the key it will
     * look that string up by. Nothing is protected here, so nothing needs hiding.
     *
     * @return IIndexHasher The memoized xxHash hasher.
     *
     * @copyright 2026 Tachyon
     */
    public function indexHasher(): IIndexHasher
    {
        return $this->indexHasher ??= new XxHasher();
    }

    /**
     * The Snowflake generator for this worker.
     *
     * Throws rather than returning a failure: a bad cluster or server id is a
     * configuration fault discovered at `WorkerStart`, where there is no request
     * to fail and a worker that cannot mint ids is not serviceable.
     *
     * @return IDatabaseIdGenerator The memoized generator.
     *
     * @throws RuntimeException When the cluster or server id is out of range.
     *
     * @copyright 2026 Tachyon
     */
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

    /**
     * The ULID generator.
     *
     * @return ISequentialIdGenerator The memoized generator.
     *
     * @copyright 2026 Tachyon
     */
    public function sequentialIdGenerator(): ISequentialIdGenerator
    {
        return $this->sequentialIdGenerator ??= new UlidGenerator();
    }

    /**
     * The NanoID generator.
     *
     * @return IRandomIdGenerator The memoized generator.
     *
     * @copyright 2026 Tachyon
     */
    public function randomIdGenerator(): IRandomIdGenerator
    {
        return $this->randomIdGenerator ??= new NanoIdGenerator();
    }

    /**
     * The user table module — the only one taking the password hasher besides
     * {@see authTM()}.
     *
     * @return IUserTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function userTM(): IUserTM
    {
        return $this->userTM ??= new UserTM($this->databaseIdGenerator(), $this->secureHasher());
    }

    /**
     * The role table module.
     *
     * @return IRoleTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function roleTM(): IRoleTM
    {
        return $this->roleTM ??= new RoleTM($this->databaseIdGenerator());
    }

    /**
     * The auth table module — verifies against the digest {@see userTM()} wrote,
     * which is why both take the same hasher.
     *
     * @return IAuthTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function authTM(): IAuthTM
    {
        return $this->authTM ??= new AuthTM($this->secureHasher());
    }

    /**
     * The product table module.
     *
     * @return IProductTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function productTM(): IProductTM
    {
        return $this->productTM ??= new ProductTM($this->databaseIdGenerator());
    }

    /**
     * The container table module.
     *
     * @return IContainerTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function containerTM(): IContainerTM
    {
        return $this->containerTM ??= new ContainerTM($this->databaseIdGenerator());
    }

    /**
     * The manifest table module.
     *
     * Takes nothing: it mints no ids and reads no storage — every input arrives
     * as an argument.
     *
     * @return IManifestTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function manifestTM(): IManifestTM
    {
        return $this->manifestTM ??= new ManifestTM();
    }

    /**
     * The permission table module.
     *
     * Takes no id generator: a permission is identified by its slug, and its
     * numeric id is a registry index assigned on insertion.
     *
     * @return IPermissionTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function permissionTM(): IPermissionTM
    {
        return $this->permissionTM ??= new PermissionTM();
    }

    /**
     * The marker-group table module. Takes nothing, for the same reason as
     * {@see permissionTM()}.
     *
     * @return IMarkerGroupTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function markerGroupTM(): IMarkerGroupTM
    {
        return $this->markerGroupTM ??= new MarkerGroupTM();
    }

    /**
     * The marker table module — the one that takes the index hasher, because it
     * is where a plain value becomes the key it is stored under.
     *
     * @return IMarkerTM The memoized table module.
     *
     * @copyright 2026 Tachyon
     */
    public function markerTM(): IMarkerTM
    {
        return $this->markerTM ??= new MarkerTM($this->indexHasher());
    }
}
