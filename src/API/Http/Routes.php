<?php

declare(strict_types=1);

namespace API\Http;

use API\Controllers\IAccountController;
use API\Controllers\IAuthController;
use API\Controllers\IContainerController;
use API\Controllers\IManifestController;
use API\Controllers\IMetricsController;
use API\Controllers\IProductController;
use API\Controllers\IRoleAdminController;
use API\Controllers\IServerController;
use API\Controllers\IUserAdminController;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

/**
 * The REST route table. Routes map to a `[ControllerInterface::class, 'method']`
 * pair; {@see \API\Http\Middleware\RouteDispatchMiddleware} resolves the
 * controller from the {@see \API\Interno\ApiProvider} registry. Path ids are
 * **base62** strings (`[A-Za-z0-9]+`), not numeric — ids travel opaque end to end.
 */
final class Routes
{
    private const string ID = '{id:[A-Za-z0-9]+}';

    public static function dispatcher(): Dispatcher
    {
        return simpleDispatcher(static function (RouteCollector $r): void {
            $r->addRoute('GET', '/info', [IServerController::class, 'getInfo']);

            // Bootstrap: the only way into a system with no users yet. Refuses
            // itself with 409 once one exists.
            $r->addRoute('POST', '/setup', [IAuthController::class, 'setup']);

            // --- Auth -----------------------------------------------------------
            $r->addRoute('POST', '/auth/login', [IAuthController::class, 'login']);
            $r->addRoute('POST', '/auth/refresh', [IAuthController::class, 'refresh']);
            $r->addRoute('POST', '/auth/logout', [IAuthController::class, 'logout']);

            // --- Account (self-service) -----------------------------------------
            $r->addRoute('GET', '/account', [IAccountController::class, 'get']);
            $r->addRoute('PUT', '/account', [IAccountController::class, 'update']);
            $r->addRoute('PUT', '/account/password', [IAccountController::class, 'changePassword']);

            // --- Products -------------------------------------------------------
            $r->addRoute('GET', '/products', [IProductController::class, 'list']);
            $r->addRoute('POST', '/products', [IProductController::class, 'create']);
            $r->addRoute('GET', '/products/'.self::ID, [IProductController::class, 'get']);
            $r->addRoute('PUT', '/products/'.self::ID, [IProductController::class, 'update']);
            $r->addRoute('DELETE', '/products/'.self::ID, [IProductController::class, 'delete']);

            // --- Roles (admin) --------------------------------------------------
            $r->addRoute('GET', '/roles', [IRoleAdminController::class, 'list']);
            $r->addRoute('POST', '/roles', [IRoleAdminController::class, 'create']);
            $r->addRoute('PUT', '/roles/'.self::ID.'/permissions', [IRoleAdminController::class, 'syncPermissions']);

            // --- Containers -----------------------------------------------------
            // /containers/summary MUST come before /containers/{id}.
            $r->addRoute('GET', '/containers', [IContainerController::class, 'list']);
            $r->addRoute('POST', '/containers', [IContainerController::class, 'create']);
            $r->addRoute('GET', '/containers/summary', [IContainerController::class, 'summary']);
            $r->addRoute('GET', '/containers/'.self::ID, [IContainerController::class, 'get']);
            $r->addRoute('PUT', '/containers/'.self::ID, [IContainerController::class, 'update']);
            $r->addRoute('DELETE', '/containers/'.self::ID, [IContainerController::class, 'delete']);
            $r->addRoute('POST', '/containers/'.self::ID.'/seal', [IContainerController::class, 'seal']);
            $r->addRoute('POST', '/containers/'.self::ID.'/dispatch', [IContainerController::class, 'dispatch']);

            // --- Manifests ------------------------------------------------------
            $r->addRoute('POST', '/manifests/load-item', [IManifestController::class, 'loadItem']);
            $r->addRoute('POST', '/manifests/unload-item', [IManifestController::class, 'unloadItem']);

            // --- Users (admin) --------------------------------------------------
            $r->addRoute('GET', '/users', [IUserAdminController::class, 'list']);
            $r->addRoute('POST', '/users', [IUserAdminController::class, 'create']);
            $r->addRoute('GET', '/users/'.self::ID, [IUserAdminController::class, 'get']);
            $r->addRoute('PUT', '/users/'.self::ID, [IUserAdminController::class, 'update']);
            $r->addRoute('DELETE', '/users/'.self::ID, [IUserAdminController::class, 'delete']);
            $r->addRoute('PUT', '/users/'.self::ID.'/password', [IUserAdminController::class, 'resetPassword']);
            $r->addRoute('PUT', '/users/'.self::ID.'/roles', [IUserAdminController::class, 'updateRoles']);

            // --- Metrics --------------------------------------------------------
            $r->addRoute('GET', '/metrics', [IMetricsController::class, 'get']);
        });
    }
}
