<?php

declare(strict_types=1);

namespace API\Http\Middleware;

use API\Http\AuthCookie;
use API\Http\HttpHeader;
use API\Http\RequestAttributes;
use API\Auth\ITokenService;
use App\Context\UserContext;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Infra\Logging\ILogger;

/**
 * Establishes the authenticated principal from the HTTP-only auth cookie.
 *
 * The JWT travels in the {@see AuthCookie} (never the `Authorization` header nor
 * the body). This middleware is non-enforcing: a request with no cookie proceeds
 * anonymously (public routes such as `POST /auth/login` must work), and a cookie
 * whose token is invalid/expired is also treated as anonymous — but the stale
 * cookie is cleared on the way out, so the browser stops resending it. When the
 * token is valid the caller's {@see UserContext} is stored in the coroutine
 * context under {@see RequestAttributes::AuthenticatedUser}.
 *
 * It reads the cookie only — never the `Authorization` header — and it does not
 * authorize: the 401 for anonymous access comes from {@see \API\Http\Session}
 * in the controller, and the 403 from the use case that owns the permission.
 */
final class AuthenticationMiddleware implements MiddlewareInterface
{
    private ILogger $logger;

    public function __construct(
        private readonly ITokenService $tokenService,
        private readonly AuthCookie $cookie,
        ILogger $logger,
    ) {
        $this->logger = $logger->withChannel('auth');
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->cookie->read($request);

        if ($token === null) {
            return $handler->handle($request);
        }

        $result = $this->tokenService->verify($token);
        if (!$result->isSuccess()) {
            // A stale/invalid cookie must not break public routes: proceed as
            // anonymous and clear the cookie so it is not resent.
            $this->logger->info('Discarding invalid authentication cookie');

            return $handler->handle($request)
                ->withHeader(HttpHeader::SetCookie->value, $this->cookie->clear());
        }

        /** @var UserContext $context */
        $context = $result->getValue();

        RequestAttributes::AuthenticatedUser->write($context);
        $this->logger->setContext('user_id', $context->id);

        return $handler->handle($request);
    }
}
