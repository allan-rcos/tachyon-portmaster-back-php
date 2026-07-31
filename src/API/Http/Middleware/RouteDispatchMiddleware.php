<?php

/**
 * Route Dispatch Middleware.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * @see \API\Http\Routes The table being matched against.
 * @uses Dispatcher Matches method and path.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class RouteDispatchMiddleware implements MiddlewareInterface
{
    /**
     * @param  Dispatcher  $dispatcher  Built from {@see \API\Http\Routes}.
     * @param  array<class-string, object>  $controllers  Controller interface => instance.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly array $controllers,
    ) {
    }

    /**
     * Matches the route and calls the controller behind it.
     *
     * A matched route whose interface is absent from the registry answers 500,
     * not 404: the route exists, so the gap is this server's wiring rather than
     * anything the caller did.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request, to
     *                                           which the path variables are
     *                                           added as attributes.
     * @param  RequestHandlerInterface  $handler  Unused — this middleware is
     *                                            terminal.
     * @return ResponseInterface The controller's response, or a 404, 405 or 500
     *                           problem document.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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
