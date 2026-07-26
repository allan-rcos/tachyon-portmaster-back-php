<?php

declare(strict_types=1);

namespace API\Controllers\Interno;

use API\Config\ApiConfig;
use API\Controllers\IServerController;
use API\Fbs\Server\ProjectInfoProxy;
use API\Http\ApiResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * `GET /info` — server metadata.
 *
 * The response is a {@see ProjectInfoProxy}, declared in `server.fbs` and
 * published in swagger.json, so it is content-negotiated like every other
 * endpoint. It previously built a `ProjectInfoDTO` and called `json_encode`
 * by hand, which put an endpoint outside the contract entirely.
 *
 * The environment is read from {@see ApiConfig} rather than hard-coded, so the
 * value actually reflects how the server was started.
 */
final readonly class ServerController implements IServerController
{
    private const string NAME = 'tachyon/portmaster';
    private const string VERSION = '0.0.1';

    public function __construct(
        private ApiConfig $config,
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getInfo(ServerRequestInterface $request): ResponseInterface
    {
        return ApiResponse::body(new ProjectInfoProxy(
            name: self::NAME,
            version: self::VERSION,
            environment: $this->config->environment->value,
            runtime: 'PHP '.PHP_VERSION.' + OpenSwoole',
            memoryUsageMb: round(memory_get_usage() / 1024 / 1024, 2),
        ));
    }
}
