<?php

declare(strict_types=1);

namespace API\Http\Middleware;

use API\Http\ProblemResponse;
use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Terminal middleware that resolves the route to a controller and invokes it.
 *
 * Routes map to a `[ControllerInterface::class, 'method']` pair (no closures);
 * the controller is looked up in the registry the {@see \API\Interno\ApiProvider}
 * assembled (no DI container), and path variables are exposed to it as request
 * attributes. The controller is expected to return a PSR-7 response.
 *
 * Being the innermost middleware, it intentionally does not delegate to
 * `$handler`: it always produces the final response (a dispatched controller
 * result, or a 404/405 problem response).
 */
final class RouteDispatchMiddleware implements MiddlewareInterface
{
    /**
     * @param  array<class-string, object>  $controllers  Controller interface => instance.
     */
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly array $controllers,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $routeInfo = $this->dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                /** @var array{0: class-string, 1: string} $target */
                $target = $routeInfo[1];
                [$controllerInterface, $method] = $target;

                /** @var array<string, string> $vars */
                $vars = $routeInfo[2];
                foreach ($vars as $name => $value) {
                    $request = $request->withAttribute($name, $value);
                }

                $controller = $this->controllers[$controllerInterface] ?? null;
                if ($controller === null) {
                    return ProblemResponse::make(
                        500,
                        'Internal Server Error',
                        sprintf('No controller registered for %s.', $controllerInterface),
                    );
                }

                /** @var ResponseInterface */
                return $controller->{$method}($request);

            case Dispatcher::METHOD_NOT_ALLOWED:
                /** @var list<string> $allowedMethods */
                $allowedMethods = $routeInfo[1];

                return ProblemResponse::make(
                    405,
                    'Method Not Allowed',
                    sprintf('The %s method is not allowed for this route.', $request->getMethod()),
                )->withHeader('Allow', implode(', ', $allowedMethods));

            case Dispatcher::NOT_FOUND:
            default:
                return ProblemResponse::make(
                    404,
                    'Not Found',
                    sprintf("The route '%s' was not found.", $request->getUri()->getPath()),
                );
        }
    }
}
