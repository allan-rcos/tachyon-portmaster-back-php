<?php

declare(strict_types=1);

namespace App;

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
use App\Services\IGetMarkerUseCase;
use App\Services\IValidateSessionUseCase;
use App\Services\IGetUserUseCase;
use Domain\ID\IRandomIdGenerator;
use Domain\ID\ISequentialIdGenerator;
use Infra\Database\IUnitOfWork;
use Infra\Logging\ILogger;

/**
 * Factory surface of the application layer — the only provider the presentations
 * (api-http, …) know. It exposes every UseCase (write Commands + read Queries)
 * and **re-exports** the infra/domain services the presentation needs
 * ({@see logger()}, {@see sequentialIdGenerator()}, …) so the API never reaches
 * into {@see \Infra} or {@see \Domain} providers directly. Built by
 * {@see \App\AppRegister::execute()}, which chains infra + domain registers.
 */
interface IAppProvider
{
    // --- Re-exported infra/domain services (for the API middlewares) ---------
    public function logger(): ILogger;

    /**
     * The boundary a caller opens around its own work.
     *
     * Re-exported for the presentation layer's session services, which run
     * *outside* any use case — the refresh flow touches markers after login has
     * already committed, so nothing else has a boundary open for it to join. Use
     * cases receive it injected and never reach for it here.
     */
    public function unitOfWork(): IUnitOfWork;

    /**
     * The sortable-id generator, for request correlation in the middlewares.
     * Persisted-entity ids are minted inside the domain, never here.
     */
    public function sequentialIdGenerator(): ISequentialIdGenerator;

    /**
     * The unguessable-id generator, used by the API to mint refresh tokens.
     */
    public function randomIdGenerator(): IRandomIdGenerator;

    /**
     * Refresh-token storage. Re-exported because the session lives in the API
     * layer, but its persistence — like every other repository — does not.
     */

    // --- Auth ---------------------------------------------------------------
    public function loginUseCase(): ILoginUseCase;

    /** Bootstraps the first user; see {@see ISetupUseCase}. */
    public function setupUseCase(): ISetupUseCase;

    public function validateSessionUseCase(): IValidateSessionUseCase;

    // --- Markers -------------------------------------------------------------

    /** Declares a marker group at WorkerStart; see {@see IRegisterMarkerGroupUseCase}. */
    public function registerMarkerGroupUseCase(): IRegisterMarkerGroupUseCase;

    public function setMarkerUseCase(): ISetMarkerUseCase;

    public function getMarkerUseCase(): IGetMarkerUseCase;

    // --- Account (self-service) ---------------------------------------------
    public function getAccountUseCase(): IGetAccountUseCase;

    public function updateAccountUseCase(): IUpdateAccountUseCase;

    public function changePasswordUseCase(): IChangePasswordUseCase;

    // --- Users (admin) ------------------------------------------------------
    public function listUsersUseCase(): IListUsersUseCase;

    public function getUserUseCase(): IGetUserUseCase;

    public function createUserUseCase(): ICreateUserUseCase;

    public function updateUserUseCase(): IUpdateUserUseCase;

    public function deleteUserUseCase(): IDeleteUserUseCase;

    public function resetUserPasswordUseCase(): IResetUserPasswordUseCase;

    public function updateUserRolesUseCase(): IUpdateUserRolesUseCase;

    // --- Roles (admin) ------------------------------------------------------
    public function listRolesUseCase(): IListRolesUseCase;

    public function createRoleUseCase(): ICreateRoleUseCase;

    public function getRoleUseCase(): IGetRoleUseCase;

    public function updateRolePermissionsUseCase(): IUpdateRolePermissionsUseCase;

    // --- Products -----------------------------------------------------------
    public function listProductsUseCase(): IListProductsUseCase;

    public function createProductUseCase(): ICreateProductUseCase;

    public function getProductUseCase(): IGetProductUseCase;

    public function updateProductUseCase(): IUpdateProductUseCase;

    public function deleteProductUseCase(): IDeleteProductUseCase;

    // --- Containers ---------------------------------------------------------
    public function listContainersUseCase(): IListContainersUseCase;

    public function createContainerUseCase(): ICreateContainerUseCase;

    public function listContainerSummariesUseCase(): IListContainerSummariesUseCase;

    public function getContainerUseCase(): IGetContainerUseCase;

    public function updateContainerUseCase(): IUpdateContainerUseCase;

    public function deleteContainerUseCase(): IDeleteContainerUseCase;

    public function sealContainerUseCase(): ISealContainerUseCase;

    public function dispatchContainerUseCase(): IDispatchContainerUseCase;

    // --- Manifests ----------------------------------------------------------
    public function loadItemUseCase(): ILoadItemUseCase;

    public function unloadItemUseCase(): IUnloadItemUseCase;

    // --- Metrics ------------------------------------------------------------
    public function getMetricsUseCase(): IGetMetricsUseCase;
}
