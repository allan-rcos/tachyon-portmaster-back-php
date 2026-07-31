<?php

/**
 * App Provider.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Interno;

use App\IAppProvider;
use App\Interno\Provider\AccountProvider;
use App\Interno\Provider\AuthProvider;
use App\Interno\Provider\ContainerProvider;
use App\Interno\Provider\ManifestProvider;
use App\Interno\Provider\MarkerProvider;
use App\Interno\Provider\MetadataProvider;
use App\Interno\Provider\MetricsProvider;
use App\Interno\Provider\ProductProvider;
use App\Interno\Provider\RoleProvider;
use App\Interno\Provider\UserProvider;
use App\Services\IChangePasswordUseCase;
use App\Services\ICreateContainerUseCase;
use App\Services\ICreateProductUseCase;
use App\Services\ICreateRoleUseCase;
use App\Services\ICreateUserUseCase;
use App\Services\IDeleteContainerUseCase;
use App\Services\IDeleteProductUseCase;
use App\Services\IDeleteUserUseCase;
use App\Services\IDispatchContainerUseCase;
use App\Services\IGetAccountUseCase;
use App\Services\IGetContainerUseCase;
use App\Services\IGetMarkerUseCase;
use App\Services\IGetMetricsUseCase;
use App\Services\IGetProductUseCase;
use App\Services\IGetRoleUseCase;
use App\Services\IGetUserUseCase;
use App\Services\IListContainerSummariesUseCase;
use App\Services\IListContainersUseCase;
use App\Services\IListPermissionsUseCase;
use App\Services\IListProductsUseCase;
use App\Services\IListRolesUseCase;
use App\Services\IListUsersUseCase;
use App\Services\ILoadItemUseCase;
use App\Services\ILoginUseCase;
use App\Services\IRegisterMarkerGroupUseCase;
use App\Services\IResetUserPasswordUseCase;
use App\Services\ISealContainerUseCase;
use App\Services\ISetMarkerUseCase;
use App\Services\ISetupUseCase;
use App\Services\IUnloadItemUseCase;
use App\Services\IUpdateAccountUseCase;
use App\Services\IUpdateContainerUseCase;
use App\Services\IUpdateProductUseCase;
use App\Services\IUpdateRolePermissionsUseCase;
use App\Services\IUpdateUserRolesUseCase;
use App\Services\IUpdateUserUseCase;
use App\Services\IValidateSessionUseCase;
use Domain\ID\IRandomIdGenerator;
use Domain\ID\ISequentialIdGenerator;
use Domain\IDomainProvider;
use Infra\Database\IUnitOfWork;
use Infra\IInfraProvider;
use Infra\Logging\ILogger;

/**
 * The application layer's façade: ten feature providers behind one surface.
 *
 * Builds nothing itself. It used to build all thirty-odd use cases, which made
 * one class the place every feature had to touch; construction now lives in the
 * per-feature providers under {@see \App\Interno\Provider} and every factory
 * here is a one-line delegation to the one that owns it. That is what lets
 * {@see IAppProvider} stay a single flat contract for the API while the wiring
 * stays readable — see {@see \App\Interno\Provider\FeatureProvider} for why it
 * was split.
 *
 * The feature providers *are* eagerly constructed, in the constructor, unlike
 * the use cases they build. They are cheap holders of two references, and having
 * them present removes a null check from forty methods.
 *
 * @see IAppProvider The contract this implements.
 * @see \App\Interno\Provider\FeatureProvider The base of the ten slices.
 * @see \App\AppRegister What builds one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class AppProvider implements IAppProvider
{
    /**
     * @var AuthProvider Login, session validation and the deployment bootstrap.
     */
    private AuthProvider $auth;

    /**
     * @var AccountProvider The caller acting on their own account; unguarded.
     */
    private AccountProvider $account;

    /**
     * @var UserProvider Administration of other people's accounts; guarded.
     */
    private UserProvider $users;

    /**
     * @var RoleProvider Roles and the permissions they grant.
     */
    private RoleProvider $roles;

    /**
     * @var ProductProvider The product catalogue.
     */
    private ProductProvider $products;

    /**
     * @var ContainerProvider Containers and their status transitions.
     */
    private ContainerProvider $containers;

    /**
     * @var ManifestProvider What containers carry.
     */
    private ManifestProvider $manifests;

    /**
     * @var MarkerProvider Expiring single-use flags, and their group registrar.
     */
    private MarkerProvider $markers;

    /**
     * @var MetricsProvider The yard-wide snapshot.
     */
    private MetricsProvider $metrics;

    /**
     * @var MetadataProvider The read side of the permission registry.
     */
    private MetadataProvider $metadata;

    /**
     * Constructs the ten feature providers over the same two lower layers.
     *
     * Handing every slice the *same* {@see IInfraProvider} is what makes them
     * share one connection pool, one transaction session and one permission
     * registry — the sharing that makes a use case's boundary cover the
     * repositories it writes through.
     *
     * @param  IDomainProvider  $domain  Supplies the table modules.
     * @param  IInfraProvider  $infra  Supplies the repositories, the boundary and
     *                                 the registry.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private IDomainProvider $domain,
        private IInfraProvider $infra,
    ) {
        $this->auth = new AuthProvider($domain, $infra);
        $this->account = new AccountProvider($domain, $infra);
        $this->users = new UserProvider($domain, $infra);
        $this->roles = new RoleProvider($domain, $infra);
        $this->products = new ProductProvider($domain, $infra);
        $this->containers = new ContainerProvider($domain, $infra);
        $this->manifests = new ManifestProvider($domain, $infra);
        $this->markers = new MarkerProvider($domain, $infra);
        $this->metrics = new MetricsProvider($domain, $infra);
        $this->metadata = new MetadataProvider($domain, $infra);
    }

    // --- Re-exported infra/domain services -----------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function logger(): ILogger
    {
        return $this->infra->logger();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function unitOfWork(): IUnitOfWork
    {
        return $this->infra->unitOfWork();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function sequentialIdGenerator(): ISequentialIdGenerator
    {
        return $this->domain->sequentialIdGenerator();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function randomIdGenerator(): IRandomIdGenerator
    {
        return $this->domain->randomIdGenerator();
    }


    // --- Auth ----------------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function loginUseCase(): ILoginUseCase
    {
        return $this->auth->loginUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function setupUseCase(): ISetupUseCase
    {
        return $this->auth->setupUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function validateSessionUseCase(): IValidateSessionUseCase
    {
        return $this->auth->validateSessionUseCase();
    }

    // --- Markers -------------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function registerMarkerGroupUseCase(): IRegisterMarkerGroupUseCase
    {
        return $this->markers->registerMarkerGroupUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function setMarkerUseCase(): ISetMarkerUseCase
    {
        return $this->markers->setMarkerUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getMarkerUseCase(): IGetMarkerUseCase
    {
        return $this->markers->getMarkerUseCase();
    }

    // --- Account (self-service) ----------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getAccountUseCase(): IGetAccountUseCase
    {
        return $this->account->getAccountUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function updateAccountUseCase(): IUpdateAccountUseCase
    {
        return $this->account->updateAccountUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function changePasswordUseCase(): IChangePasswordUseCase
    {
        return $this->account->changePasswordUseCase();
    }

    // --- Users (admin) -------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function listUsersUseCase(): IListUsersUseCase
    {
        return $this->users->listUsersUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getUserUseCase(): IGetUserUseCase
    {
        return $this->users->getUserUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function createUserUseCase(): ICreateUserUseCase
    {
        return $this->users->createUserUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function updateUserUseCase(): IUpdateUserUseCase
    {
        return $this->users->updateUserUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function deleteUserUseCase(): IDeleteUserUseCase
    {
        return $this->users->deleteUserUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function resetUserPasswordUseCase(): IResetUserPasswordUseCase
    {
        return $this->users->resetUserPasswordUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function updateUserRolesUseCase(): IUpdateUserRolesUseCase
    {
        return $this->users->updateUserRolesUseCase();
    }

    // --- Roles ---------------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function listRolesUseCase(): IListRolesUseCase
    {
        return $this->roles->listRolesUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getRoleUseCase(): IGetRoleUseCase
    {
        return $this->roles->getRoleUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function createRoleUseCase(): ICreateRoleUseCase
    {
        return $this->roles->createRoleUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function updateRolePermissionsUseCase(): IUpdateRolePermissionsUseCase
    {
        return $this->roles->updateRolePermissionsUseCase();
    }

    // --- System metadata -----------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function listPermissionsUseCase(): IListPermissionsUseCase
    {
        return $this->metadata->listPermissionsUseCase();
    }

    // --- Products ------------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function listProductsUseCase(): IListProductsUseCase
    {
        return $this->products->listProductsUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getProductUseCase(): IGetProductUseCase
    {
        return $this->products->getProductUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function createProductUseCase(): ICreateProductUseCase
    {
        return $this->products->createProductUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function updateProductUseCase(): IUpdateProductUseCase
    {
        return $this->products->updateProductUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function deleteProductUseCase(): IDeleteProductUseCase
    {
        return $this->products->deleteProductUseCase();
    }

    // --- Containers ----------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function listContainersUseCase(): IListContainersUseCase
    {
        return $this->containers->listContainersUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function listContainerSummariesUseCase(): IListContainerSummariesUseCase
    {
        return $this->containers->listContainerSummariesUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getContainerUseCase(): IGetContainerUseCase
    {
        return $this->containers->getContainerUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function createContainerUseCase(): ICreateContainerUseCase
    {
        return $this->containers->createContainerUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function updateContainerUseCase(): IUpdateContainerUseCase
    {
        return $this->containers->updateContainerUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function deleteContainerUseCase(): IDeleteContainerUseCase
    {
        return $this->containers->deleteContainerUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function sealContainerUseCase(): ISealContainerUseCase
    {
        return $this->containers->sealContainerUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function dispatchContainerUseCase(): IDispatchContainerUseCase
    {
        return $this->containers->dispatchContainerUseCase();
    }

    // --- Manifests -----------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function loadItemUseCase(): ILoadItemUseCase
    {
        return $this->manifests->loadItemUseCase();
    }

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function unloadItemUseCase(): IUnloadItemUseCase
    {
        return $this->manifests->unloadItemUseCase();
    }

    // --- Metrics -------------------------------------------------------------

    /**
     * @inheritDoc
     *
     * @copyright 2026 Tachyon
     */
    public function getMetricsUseCase(): IGetMetricsUseCase
    {
        return $this->metrics->getMetricsUseCase();
    }
}
