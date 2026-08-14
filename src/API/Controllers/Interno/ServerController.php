<?php

/**
 * Server Controller.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Config\ApiConfig;
use API\Controllers\IServerController;
use API\Http\ApiResponse;
use API\Http\ProblemResponse;
use API\Negociation\DTO\Server\ProjectInfoX;
use API\Negociation\DTO\Server\ProjectInfoXFactory;
use API\Negociation\IAcceptsStrategy;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `GET /info` — server metadata.
 *
 * The response is a {@see ProjectInfoX}, declared in `server.fbs` and
 * published in swagger.json, so it is content-negotiated like every other
 * endpoint — an endpoint that hand-rolled its own JSON would be one no
 * client could discover.
 *
 * The environment is read from {@see ApiConfig} rather than hard-coded, so the
 * value actually reflects how the server was started.
 *
 * The one controller that resolves no caller: `/info` is reachable
 * unauthenticated, and it reports on the process rather than on any data.
 *
 * @see IServerController The contract this implements.
 * @see ProductController The action shape the guarded controllers follow.
 * @uses ApiConfig Supplies the environment.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class ServerController implements IServerController
{
    /**
     * The published project name.
     *
     * @var string
     */
    private const string NAME = 'tachyon/portmaster';

    /**
     * The published version. Not read from `composer.json`, so it is updated by
     * hand at release.
     *
     * @var string
     */
    private const string VERSION = '1.2.0';

    /**
     * @param  ApiConfig  $config  Supplies the reported environment.
     * @param  IAcceptsStrategy  $accepts  Renders the response body.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        private ApiConfig $config,
        private IAcceptsStrategy $accepts,
    ) {
    }

    /**
     * {@inheritDoc}
     *
     * The memory figure is this worker's, read at the moment of the call — with
     * several workers, successive requests will report different numbers.
     *
     * @param  ServerRequestInterface  $request  The incoming HTTP request.
     * @return ResponseInterface A `ProjectInfoX`.
     *
     * @copyright 2026 Tachyon
     */
    public function getInfo(ServerRequestInterface $request): ResponseInterface
    {
        $response = ApiResponse::body($this->accepts, new ProjectInfoXFactory(new ProjectInfoX(
            name: self::NAME,
            version: self::VERSION,
            environment: $this->config->environment->value,
            runtime: 'PHP '.PHP_VERSION.' + OpenSwoole',
            memoryUsageMb: round(memory_get_usage() / 1024 / 1024, 2),
        )));

        return $response->isSuccess()
            ? $response->getValue()
            : ProblemResponse::fromResult($this->accepts, $response);
    }
}
