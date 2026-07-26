<?php

declare(strict_types=1);

namespace App\Interno;

use App\IAppProvider;
use App\Interno\Provider\AccountProvider;
use App\Interno\Provider\AuthProvider;
use App\Interno\Provider\ContainerProvider;
use App\Interno\Provider\ManifestProvider;
use App\Interno\Provider\MarkerProvider;
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
use App\Services\IGetMetricsUseCase;
use App\Services\IGetProductUseCase;
use App\Services\IGetRoleUseCase;
use App\Services\IGetMarkerUseCase;
use App\Services\IValidateSessionUseCase;
use App\Services\IGetUserUseCase;
use App\Services\IListContainerSummariesUseCase;
use App\Services\IListContainersUseCase;
use App\Services\IListProductsUseCase;
use App\Services\IListRolesUseCase;
use App\Services\IListUsersUseCase;
use App\Services\ILoadItemUseCase;
use App\Services\ILoginUseCase;
use App\Services\IRegisterMarkerGroupUseCase;
use App\Services\ISetMarkerUseCase;
use App\Services\ISetupUseCase;
use App\Services\IResetUserPasswordUseCase;
use App\Services\ISealContainerUseCase;
use App\Services\IUnloadItemUseCase;
use App\Services\IUpdateAccountUseCase;
use App\Services\IUpdateContainerUseCase;
use App\Services\IUpdateProductUseCase;
use App\Services\IUpdateRolePermissionsUseCase;
use App\Services\IUpdateUserRolesUseCase;
use App\Services\IUpdateUserUseCase;
use Domain\ID\IRandomIdGenerator;
use Domain\ID\ISequentialIdGenerator;
use Domain\IDomainProvider;
use Infra\IInfraProvider;
use Infra\Database\IUnitOfWork;
use Infra\Logging\ILogger;

/**
 * The application layer's single façade — **re-export only**.
 *
 * It used to build all thirty-odd use cases itself, which made one class the
 * place every feature had to touch. Construction now lives in the per-feature
 * providers under {@see \App\Interno\Provider}; this class just delegates, so
 * adding a use case means editing its feature's provider, not this file.
 */
final readonly class AppProvider implements IAppProvider
{
    private AuthProvider $auth;
    private AccountProvider $account;
    private UserProvider $users;
    private RoleProvider $roles;
    private ProductProvider $products;
    private ContainerProvider $containers;
    private ManifestProvider $manifests;
    private MarkerProvider $markers;
    private MetricsProvider $metrics;

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
    }

    // --- Re-exported infra/domain services -----------------------------------

    public function logger(): ILogger
    {
        return $this->infra->logger();
    }

    public function unitOfWork(): IUnitOfWork
    {
        return $this->infra->unitOfWork();
    }

    public function sequentialIdGenerator(): ISequentialIdGenerator
    {
        return $this->domain->sequentialIdGenerator();
    }

    public function randomIdGenerator(): IRandomIdGenerator
    {
        return $this->domain->randomIdGenerator();
    }


    // --- Auth ----------------------------------------------------------------

    public function loginUseCase(): ILoginUseCase
    {
        return $this->auth->loginUseCase();
    }

    public function setupUseCase(): ISetupUseCase
    {
        return $this->auth->setupUseCase();
    }

    public function validateSessionUseCase(): IValidateSessionUseCase
    {
        return $this->auth->validateSessionUseCase();
    }

    // --- Markers -------------------------------------------------------------

    public function registerMarkerGroupUseCase(): IRegisterMarkerGroupUseCase
    {
        return $this->markers->registerMarkerGroupUseCase();
    }

    public function setMarkerUseCase(): ISetMarkerUseCase
    {
        return $this->markers->setMarkerUseCase();
    }

    public function getMarkerUseCase(): IGetMarkerUseCase
    {
        return $this->markers->getMarkerUseCase();
    }

    // --- Account (self-service) ----------------------------------------------

    public function getAccountUseCase(): IGetAccountUseCase
    {
        return $this->account->getAccountUseCase();
    }

    public function updateAccountUseCase(): IUpdateAccountUseCase
    {
        return $this->account->updateAccountUseCase();
    }

    public function changePasswordUseCase(): IChangePasswordUseCase
    {
        return $this->account->changePasswordUseCase();
    }

    // --- Users (admin) -------------------------------------------------------

    public function listUsersUseCase(): IListUsersUseCase
    {
        return $this->users->listUsersUseCase();
    }

    public function getUserUseCase(): IGetUserUseCase
    {
        return $this->users->getUserUseCase();
    }

    public function createUserUseCase(): ICreateUserUseCase
    {
        return $this->users->createUserUseCase();
    }

    public function updateUserUseCase(): IUpdateUserUseCase
    {
        return $this->users->updateUserUseCase();
    }

    public function deleteUserUseCase(): IDeleteUserUseCase
    {
        return $this->users->deleteUserUseCase();
    }

    public function resetUserPasswordUseCase(): IResetUserPasswordUseCase
    {
        return $this->users->resetUserPasswordUseCase();
    }

    public function updateUserRolesUseCase(): IUpdateUserRolesUseCase
    {
        return $this->users->updateUserRolesUseCase();
    }

    // --- Roles ---------------------------------------------------------------

    public function listRolesUseCase(): IListRolesUseCase
    {
        return $this->roles->listRolesUseCase();
    }

    public function getRoleUseCase(): IGetRoleUseCase
    {
        return $this->roles->getRoleUseCase();
    }

    public function createRoleUseCase(): ICreateRoleUseCase
    {
        return $this->roles->createRoleUseCase();
    }

    public function updateRolePermissionsUseCase(): IUpdateRolePermissionsUseCase
    {
        return $this->roles->updateRolePermissionsUseCase();
    }

    // --- Products ------------------------------------------------------------

    public function listProductsUseCase(): IListProductsUseCase
    {
        return $this->products->listProductsUseCase();
    }

    public function getProductUseCase(): IGetProductUseCase
    {
        return $this->products->getProductUseCase();
    }

    public function createProductUseCase(): ICreateProductUseCase
    {
        return $this->products->createProductUseCase();
    }

    public function updateProductUseCase(): IUpdateProductUseCase
    {
        return $this->products->updateProductUseCase();
    }

    public function deleteProductUseCase(): IDeleteProductUseCase
    {
        return $this->products->deleteProductUseCase();
    }

    // --- Containers ----------------------------------------------------------

    public function listContainersUseCase(): IListContainersUseCase
    {
        return $this->containers->listContainersUseCase();
    }

    public function listContainerSummariesUseCase(): IListContainerSummariesUseCase
    {
        return $this->containers->listContainerSummariesUseCase();
    }

    public function getContainerUseCase(): IGetContainerUseCase
    {
        return $this->containers->getContainerUseCase();
    }

    public function createContainerUseCase(): ICreateContainerUseCase
    {
        return $this->containers->createContainerUseCase();
    }

    public function updateContainerUseCase(): IUpdateContainerUseCase
    {
        return $this->containers->updateContainerUseCase();
    }

    public function deleteContainerUseCase(): IDeleteContainerUseCase
    {
        return $this->containers->deleteContainerUseCase();
    }

    public function sealContainerUseCase(): ISealContainerUseCase
    {
        return $this->containers->sealContainerUseCase();
    }

    public function dispatchContainerUseCase(): IDispatchContainerUseCase
    {
        return $this->containers->dispatchContainerUseCase();
    }

    // --- Manifests -----------------------------------------------------------

    public function loadItemUseCase(): ILoadItemUseCase
    {
        return $this->manifests->loadItemUseCase();
    }

    public function unloadItemUseCase(): IUnloadItemUseCase
    {
        return $this->manifests->unloadItemUseCase();
    }

    // --- Metrics -------------------------------------------------------------

    public function getMetricsUseCase(): IGetMetricsUseCase
    {
        return $this->metrics->getMetricsUseCase();
    }
}
