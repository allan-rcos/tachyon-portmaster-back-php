<?php

/**
 * API Provider.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
use API\Controllers\IMetadataController;
use API\Controllers\IMetricsController;
use API\Controllers\Interno\AccountController;
use API\Controllers\Interno\AuthController;
use API\Controllers\Interno\ContainerController;
use API\Controllers\Interno\ManifestController;
use API\Controllers\Interno\MetadataController;
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
use API\Http\Router\RouterHub;
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
 *
 * @see IApiProvider The contract this implements.
 * @uses IAppProvider Supplies every use case the controllers run on.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class ApiProvider implements IApiProvider
{
    /**
     * @var AuthCookie|null Memoized; see {@see authCookie()}.
     */
    private ?AuthCookie $authCookie = null;

    /**
     * @var ITokenService|null Memoized; see {@see tokenService()}.
     */
    private ?ITokenService $tokenService = null;

    /**
     * @var IRefreshTokenService|null Memoized; see {@see refreshTokenService()}.
     */
    private ?IRefreshTokenService $refreshTokenService = null;

    /**
     * @param  IAppProvider  $app  The application layer's factory surface.
     * @param  ApiConfig  $api  HTTP server settings.
     * @param  JwtConfig  $jwt  Session token and cookie settings.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly IAppProvider $app,
        private readonly ApiConfig $api,
        private readonly JwtConfig $jwt,
    ) {
    }

    /**
     * Builds the controller registry and the middleware stack around it.
     *
     * **The stack's order is declared here, and it is the first argument that
     * runs first.** Outermost to innermost: recover (so nothing escapes),
     * request id (so everything after it can be correlated), access logging,
     * content negotiation, authentication, then route dispatch, which is
     * terminal and never delegates.
     *
     * @return RequestHandlerInterface The assembled handler.
     *
     * @copyright 2026 Tachyon
     */
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
            IMetadataController::class => new MetadataController(
                $this->app->listPermissionsUseCase(),
            ),
        ];

        return new StackHandler(
            new RecovererMiddleware($logger),
            new RequestIdMiddleware($this->app->sequentialIdGenerator(), $logger),
            new LoggingMiddleware($logger),
            new FlatBufferNegotiationMiddleware($logger),
            new AuthenticationMiddleware($this->tokenService(), $this->authCookie(), $logger),
            new RouteDispatchMiddleware(RouterHub::dispatcher(), $controllers),
        );
    }

    /**
     * The cookie reader/writer, memoized.
     *
     * Memoization matters here: the middleware and the auth controller must
     * agree on the cookie names and flags, and sharing one instance is what
     * guarantees they do.
     *
     * @return AuthCookie The single instance for this worker.
     *
     * @copyright 2026 Tachyon
     */
    private function authCookie(): AuthCookie
    {
        return $this->authCookie ??= new AuthCookie($this->jwt);
    }

    /**
     * The token signer/verifier, memoized.
     *
     * @return ITokenService The single instance for this worker.
     *
     * @copyright 2026 Tachyon
     */
    private function tokenService(): ITokenService
    {
        return $this->tokenService ??= new FirebaseJwtTokenService($this->jwt, $this->app->randomIdGenerator());
    }

    /**
     * The refresh-token service, memoized.
     *
     * Constructing it registers the `refresh-token` marker group, so it must be
     * built exactly once — which is what the memoization here is for, rather
     * than saving an allocation.
     *
     * @return IRefreshTokenService The single instance for this worker.
     *
     * @copyright 2026 Tachyon
     */
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
