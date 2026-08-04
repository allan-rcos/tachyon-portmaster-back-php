<?php

/**
 * Version 1 Router.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Http\Router\Interno;

use API\Controllers\IAccountController;
use API\Controllers\IAuthController;
use API\Controllers\IContainerController;
use API\Controllers\IManifestController;
use API\Controllers\IMetadataController;
use API\Controllers\IMetricsController;
use API\Controllers\IProductController;
use API\Controllers\IRoleAdminController;
use API\Controllers\IServerController;
use API\Controllers\IUserAdminController;
use API\Http\Router\IVersionedRouter;
use API\Http\Router\Route;
use Ds\Set;

/**
 * The first published version of the REST contract, mounted under `/v1`.
 *
 * Routes name a `[ControllerInterface::class, 'method']` pair;
 * {@see \API\Http\Middleware\RouteDispatchMiddleware} resolves the controller
 * from the {@see \API\Interno\ApiProvider} registry. Routing to the *interface*
 * rather than a concrete controller is what keeps this the only place a URL
 * appears: nothing here names an implementation, so swapping one changes
 * nothing in this file.
 *
 * This table is **frozen**. A change to what any of these routes means is a
 * `V2Router` beside this file, not an edit to it — that is the whole point of
 * the version being a class.
 *
 * @see \API\Http\Router\RouterHub What mounts this.
 * @see \API\Http\Middleware\RouteDispatchMiddleware What matches against it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class V1Router implements IVersionedRouter
{
    /**
     * The path-id placeholder, base62 rather than numeric.
     *
     * Private to this version, not shared through the contract: how an id looks
     * on the wire is part of what v1 promises, and a later version is free to
     * promise something else.
     *
     * @var string
     */
    private const string ID = '{id:[A-Za-z0-9]+}';

    /**
     * {@inheritDoc}
     *
     * @return int Always 1.
     *
     * @copyright 2026 Tachyon
     */
    public function getVersion(): int
    {
        return 1;
    }

    /**
     * {@inheritDoc}
     *
     * @return Set<Route> The 24 resources of the v1 contract.
     *
     * @copyright 2026 Tachyon
     */
    public function routes(): Set
    {
        return new Set([
            new Route('GET', '/info', [IServerController::class, 'getInfo']),

            // Bootstrap: the only way into a system with no users yet. Refuses
            // itself with 409 once one exists.
            new Route('POST', '/setup', [IAuthController::class, 'setup']),

            // --- Auth -------------------------------------------------------
            new Route('POST', '/auth/login', [IAuthController::class, 'login']),
            new Route('POST', '/auth/refresh', [IAuthController::class, 'refresh']),
            new Route('POST', '/auth/logout', [IAuthController::class, 'logout']),

            // --- Account (self-service) -------------------------------------
            new Route('GET', '/account', [IAccountController::class, 'get']),
            new Route('PUT', '/account', [IAccountController::class, 'update']),
            new Route('PUT', '/account/password', [IAccountController::class, 'changePassword']),

            // --- Products ---------------------------------------------------
            new Route('GET', '/products', [IProductController::class, 'list']),
            new Route('POST', '/products', [IProductController::class, 'create']),
            new Route('GET', '/products/'.self::ID, [IProductController::class, 'get']),
            new Route('PUT', '/products/'.self::ID, [IProductController::class, 'update']),
            new Route('DELETE', '/products/'.self::ID, [IProductController::class, 'delete']),

            // --- Roles (admin) ----------------------------------------------
            new Route('GET', '/roles', [IRoleAdminController::class, 'list']),
            new Route('POST', '/roles', [IRoleAdminController::class, 'create']),
            new Route('PUT', '/roles/'.self::ID.'/permissions', [IRoleAdminController::class, 'syncPermissions']),

            // --- Containers -------------------------------------------------
            // /containers/summary MUST come before /containers/{id}.
            new Route('GET', '/containers', [IContainerController::class, 'list']),
            new Route('POST', '/containers', [IContainerController::class, 'create']),
            new Route('GET', '/containers/summary', [IContainerController::class, 'summary']),
            new Route('GET', '/containers/'.self::ID, [IContainerController::class, 'get']),
            new Route('PUT', '/containers/'.self::ID, [IContainerController::class, 'update']),
            new Route('DELETE', '/containers/'.self::ID, [IContainerController::class, 'delete']),
            new Route('POST', '/containers/'.self::ID.'/seal', [IContainerController::class, 'seal']),
            new Route('POST', '/containers/'.self::ID.'/dispatch', [IContainerController::class, 'dispatch']),

            // --- Manifests --------------------------------------------------
            new Route('POST', '/manifests/load-item', [IManifestController::class, 'loadItem']),
            new Route('POST', '/manifests/unload-item', [IManifestController::class, 'unloadItem']),

            // --- Users (admin) ----------------------------------------------
            new Route('GET', '/users', [IUserAdminController::class, 'list']),
            new Route('POST', '/users', [IUserAdminController::class, 'create']),
            new Route('GET', '/users/'.self::ID, [IUserAdminController::class, 'get']),
            new Route('PUT', '/users/'.self::ID, [IUserAdminController::class, 'update']),
            new Route('DELETE', '/users/'.self::ID, [IUserAdminController::class, 'delete']),
            new Route('PUT', '/users/'.self::ID.'/password', [IUserAdminController::class, 'resetPassword']),
            new Route('PUT', '/users/'.self::ID.'/roles', [IUserAdminController::class, 'updateRoles']),

            // --- System metadata --------------------------------------------
            // The catalogue is filled from code at WorkerStart, so this is the
            // whole of the resource: no ids, no writes.
            new Route('GET', '/metadata/permissions', [IMetadataController::class, 'listPermissions']),

            // --- Metrics ----------------------------------------------------
            new Route('GET', '/metrics', [IMetricsController::class, 'get']),
        ]);
    }
}
