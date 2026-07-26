<?php

declare(strict_types=1);

namespace API\Interno;

use API\Auth\Interno\FirebaseJwtTokenService;
use API\Auth\Interno\RefreshTokenService;
use API\Auth\IRefreshTokenService;
use API\Auth\ITokenService;
use API\Config\ApiConfig;
use API\Config\JwtConfig;
use API\Controllers\IAccountController;
use API\Controllers\IAuthController;
use API\Controllers\IContainerController;
use API\Controllers\IManifestController;
use API\Controllers\IMetricsController;
use API\Controllers\Interno\AccountController;
use API\Controllers\Interno\AuthController;
use API\Controllers\Interno\ContainerController;
use API\Controllers\Interno\ManifestController;
use API\Controllers\Interno\MetricsController;
use API\Controllers\Interno\ProductController;
use API\Controllers\Interno\RoleAdminController;
use API\Controllers\Interno\ServerController;
use API\Controllers\Interno\UserAdminController;
use API\Controllers\IProductController;
use API\Controllers\IRoleAdminController;
use API\Controllers\IServerController;
use API\Controllers\IUserAdminController;
use API\Http\AuthCookie;
use API\Http\Middleware\AuthenticationMiddleware;
use API\Http\Middleware\FlatBufferNegotiationMiddleware;
use API\Http\Middleware\LoggingMiddleware;
use API\Http\Middleware\RecovererMiddleware;
use API\Http\Middleware\RequestIdMiddleware;
use API\Http\Middleware\RouteDispatchMiddleware;
use API\Http\Routes;
use API\IApiProvider;
use App\IAppProvider;
use OpenSwoole\Core\Psr\Middleware\StackHandler;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Hand-wired presentation provider. Builds the controller registry from the app
 * provider's UseCases and assembles the PSR-15 middleware stack. The router is
 * built once per worker (from {@see \API\ApiRegister} at WorkerStart).
 *
 * The session services live here, not in infra: JWTs, cookies and refresh tokens
 * are how *this* presentation carries a session, and no inner layer knows about
 * them.
 */
final class ApiProvider implements IApiProvider
{
    private ?AuthCookie $authCookie = null;
    private ?ITokenService $tokenService = null;
    private ?IRefreshTokenService $refreshTokenService = null;

    public function __construct(
        private readonly IAppProvider $app,
        private readonly ApiConfig $api,
        private readonly JwtConfig $jwt,
    ) {
    }

    public function router(): RequestHandlerInterface
    {
        $logger = $this->app->logger();

        /** @var array<class-string, object> $controllers */
        $controllers = [
            IServerController::class => new ServerController($this->api),
            IAuthController::class => new AuthController(
                $this->app->loginUseCase(),
                $this->app->setupUseCase(),
                $this->tokenService(),
                $this->refreshTokenService(),
                $this->authCookie(),
                $logger,
            ),
            IAccountController::class => new AccountController(
                $this->app->getAccountUseCase(),
                $this->app->updateAccountUseCase(),
                $this->app->changePasswordUseCase(),
            ),
            IProductController::class => new ProductController(
                $this->app->listProductsUseCase(),
                $this->app->createProductUseCase(),
                $this->app->getProductUseCase(),
                $this->app->updateProductUseCase(),
                $this->app->deleteProductUseCase(),
            ),
            IRoleAdminController::class => new RoleAdminController(
                $this->app->listRolesUseCase(),
                $this->app->createRoleUseCase(),
                $this->app->updateRolePermissionsUseCase(),
                $this->app->getRoleUseCase(),
            ),
            IContainerController::class => new ContainerController(
                $this->app->listContainersUseCase(),
                $this->app->createContainerUseCase(),
                $this->app->listContainerSummariesUseCase(),
                $this->app->getContainerUseCase(),
                $this->app->updateContainerUseCase(),
                $this->app->deleteContainerUseCase(),
                $this->app->sealContainerUseCase(),
                $this->app->dispatchContainerUseCase(),
            ),
            IManifestController::class => new ManifestController(
                $this->app->loadItemUseCase(),
                $this->app->unloadItemUseCase(),
            ),
            IUserAdminController::class => new UserAdminController(
                $this->app->listUsersUseCase(),
                $this->app->createUserUseCase(),
                $this->app->getUserUseCase(),
                $this->app->updateUserUseCase(),
                $this->app->deleteUserUseCase(),
                $this->app->resetUserPasswordUseCase(),
                $this->app->updateUserRolesUseCase(),
            ),
            IMetricsController::class => new MetricsController(
                $this->app->getMetricsUseCase(),
            ),
        ];

        return new StackHandler(
            new RecovererMiddleware($logger),
            new RequestIdMiddleware($this->app->sequentialIdGenerator(), $logger),
            new LoggingMiddleware($logger),
            new FlatBufferNegotiationMiddleware($logger),
            new AuthenticationMiddleware($this->tokenService(), $this->authCookie(), $logger),
            new RouteDispatchMiddleware(Routes::dispatcher(), $controllers),
        );
    }

    private function authCookie(): AuthCookie
    {
        return $this->authCookie ??= new AuthCookie($this->jwt);
    }

    private function tokenService(): ITokenService
    {
        return $this->tokenService ??= new FirebaseJwtTokenService($this->jwt, $this->app->randomIdGenerator());
    }

    private function refreshTokenService(): IRefreshTokenService
    {
        return $this->refreshTokenService ??= new RefreshTokenService(
            $this->tokenService(),
            $this->app->validateSessionUseCase(),
            $this->app->setMarkerUseCase(),
            $this->app->getMarkerUseCase(),
            $this->app->unitOfWork(),
            $this->jwt,
            $this->app->registerMarkerGroupUseCase(),
        );
    }
}
