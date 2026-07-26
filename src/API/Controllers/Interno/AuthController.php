<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Auth\IRefreshTokenService;
use API\Auth\ITokenService;
use API\Controllers\IAuthController;
use API\Fbs\Auth\LoginRequestProxy;
use API\Fbs\Auth\SetupRequestProxy;
use API\Fbs\Auth\LoginResponseProxy;
use API\Fbs\Auth\UserProxy;
use API\Http\ApiResponse;
use API\Http\AuthCookie;
use API\Http\HttpHeader;
use API\Http\ProblemResponse;
use App\Commands\LoginCommand;
use App\Commands\SetupCommand;
use App\Services\ILoginUseCase;
use App\Services\ISetupUseCase;
use Domain\Models\IUser;
use Infra\Logging\ILogger;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Session lifecycle: login, refresh, logout.
 *
 * Both tokens travel only as HTTP-only cookies. The access token is *also*
 * echoed in `LoginResponse.token`, which the published contract keeps for
 * compatibility — but no other endpoint reads it: the
 * {@see \API\Http\Middleware\AuthenticationMiddleware} accepts the cookie alone,
 * never the `Authorization` header.
 */
final readonly class AuthController implements IAuthController
{
    private ILogger $logger;

    public function __construct(
        private readonly ILoginUseCase $loginUseCase,
        private readonly ISetupUseCase $setupUseCase,
        private readonly ITokenService $tokenService,
        private readonly IRefreshTokenService $refreshTokens,
        private readonly AuthCookie $authCookie,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('AuthController');
    }

    public function setup(ServerRequestInterface $request): ResponseInterface
    {
        $payload = SetupRequestProxy::fromStream($request->getBody());

        $result = $this->setupUseCase->execute(new SetupCommand(
            name: $payload->name ?? '',
            email: $payload->email ?? '',
            password: $payload->password ?? '',
        ));

        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IUser $user */
        $user = $result->getValue();

        $this->logger->info('System set up', ['user_id' => $user->id]);

        // The operator who just bootstrapped the system is logged in on the
        // spot: making them turn around and call /auth/login would be ceremony,
        // and the credentials are the ones they supplied a line ago.
        return $this->issueSession($user, 201);
    }

    public function login(ServerRequestInterface $request): ResponseInterface
    {
        $credentials = LoginRequestProxy::fromStream($request->getBody());

        $result = $this->loginUseCase->execute(new LoginCommand(
            email: $credentials->email ?? '',
            password: $credentials->password ?? '',
        ));

        if (!$result->isSuccess()) {
            return ProblemResponse::fromResult($result);
        }

        /** @var IUser $user */
        $user = $result->getValue();

        $this->logger->info('User logged in', ['user_id' => $user->id]);

        return $this->issueSession($user, 200);
    }

    /**
     * Mints both tokens for a user and answers with the session body plus the
     * two cookies.
     *
     * Shared by {@see setup()} and {@see login()}: they differ only in how the
     * user was established and in the status code, and duplicating the cookie
     * wiring is exactly how one of them would quietly stop setting the refresh
     * cookie.
     */
    private function issueSession(IUser $user, int $status): ResponseInterface
    {
        $refresh = $this->refreshTokens->issue($user);
        if (!$refresh->isSuccess()) {
            return ProblemResponse::fromResult($refresh);
        }

        /** @var string $refreshToken */
        $refreshToken = $refresh->getValue();
        $accessToken = $this->tokenService->issue($user);

        $body = new LoginResponseProxy(
            token: $accessToken,
            tokenType: 'cookie',
            user: new UserProxy(id: $user->id, name: $user->name, email: $user->email),
        );

        return ApiResponse::body($body, $status)
            ->withAddedHeader(HttpHeader::SetCookie->value, $this->authCookie->issue($accessToken))
            ->withAddedHeader(HttpHeader::SetCookie->value, $this->authCookie->issueRefresh($refreshToken));
    }

    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        $presented = $this->authCookie->readRefresh($request);

        if ($presented === null) {
            return ProblemResponse::fromResult(self::missingRefreshToken());
        }

        $rotated = $this->refreshTokens->rotate($presented);
        if (!$rotated->isSuccess()) {
            // The presented token is dead; clear both cookies so the client
            // stops resending it and is forced back through login.
            return ProblemResponse::fromResult($rotated)
                ->withAddedHeader(HttpHeader::SetCookie->value, $this->authCookie->clear())
                ->withAddedHeader(HttpHeader::SetCookie->value, $this->authCookie->clearRefresh());
        }

        /** @var array{user: IUser, token: string} $rotation */
        $rotation = $rotated->getValue();

        // The rotation already re-read the user, so these roles/permissions are
        // current rather than copied forward from the expiring token's claims —
        // otherwise a revoked permission would survive every refresh.
        $user = $rotation['user'];

        $this->logger->info('Session refreshed', ['user_id' => $user->id]);

        return ApiResponse::noContent()
            ->withAddedHeader(
                HttpHeader::SetCookie->value,
                $this->authCookie->issue($this->tokenService->issue($user)),
            )
            ->withAddedHeader(
                HttpHeader::SetCookie->value,
                $this->authCookie->issueRefresh($rotation['token']),
            );
    }

    public function logout(ServerRequestInterface $request): ResponseInterface
    {
        $presented = $this->authCookie->readRefresh($request);

        if ($presented !== null) {
            // Best effort: a failed revoke must not stop the client from being
            // logged out locally, so the result is logged, not returned.
            $revoked = $this->refreshTokens->revoke($presented);
            if (!$revoked->isSuccess()) {
                $this->logger->warn('Could not revoke refresh token on logout');
            }
        }

        return ApiResponse::noContent()
            ->withAddedHeader(HttpHeader::SetCookie->value, $this->authCookie->clear())
            ->withAddedHeader(HttpHeader::SetCookie->value, $this->authCookie->clearRefresh());
    }

    /**
     * @return Result<never>
     */
    private static function missingRefreshToken(): Result
    {
        return Result::failure(Leaf::newError(new LeafContext(
            message: 'Invalid or expired refresh token.',
            code: 401,
        )));
    }
}
