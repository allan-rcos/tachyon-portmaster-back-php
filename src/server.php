<?php

/**
 * Main Application Bootstrapper and Request Mapping Structure Module.
 *
 * A module initiating runtime values mapping bindings logic executions endpoints configurations setup properties layouts.
 *
 * @category Server Core Definition Executions
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @filesource
 */

require_once __DIR__.'/../vendor/autoload.php';

use API\Controllers\IServerController;
use DI\Container;
use DI\ContainerBuilder;
use Domain\Ports\Core\IIntIdGenerator;
use FastRoute\Dispatcher;
use Infra\Core\DotEnvStarter;
use OpenSwoole\Coroutine;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use OpenSwoole\Runtime;
use Shared\Config\ServerConfigEnvironmentEnum;
use Shared\Exceptions\LeafContext;
use Shared\ID\SnowflakeIdGenerator;
use Shared\Logging\ILogger;
use Shared\Logging\MonologFactory;
use function FastRoute\simpleDispatcher;

// Swoole runtime configuration and initialization logic
Runtime::enableCoroutine();

/**
 * Main application entry point for configuring environment dependencies structures loading setup routines execution.
 */
$config = DotEnvStarter::start_from_env(__DIR__.'/..');
if ($config instanceof LeafContext) {
    echo "Error loading environment variables: $config->message\n";
    foreach ($config->details as $key => $error) {
        $keyStr = is_scalar($key) ? (string) $key : '';
        $errorStr = is_scalar($error) ? (string) $error : '';
        echo "- $keyStr: $errorStr\n";
    }
    exit(1);
}

$logger = MonologFactory::create(level: $config->logLevel);

/** @var Container|null $container */
$container = null;

$dispatcher = simpleDispatcher(function(FastRoute\RouteCollector $r) {
    $r->addRoute('GET', '/info', [IServerController::class, 'getInfo']);
});

$server = new Server($config->host, $config->port);

$server->on("start", function(Server $server) use (&$logger) {
    $logger->info("Swoole API Stack [Clean Arch Stage 1] started", [
        'host' => $server->host,
        'port' => $server->port
    ]);
});

$server->on("WorkerStart",
    function(Server $server, int $workerID) use (
        &$container,
        &$logger,
        $config
    ) {

        $containerBuilder = new ContainerBuilder();

        $containerBuilder->addDefinitions([
            ILogger::class => $logger,
            IIntIdGenerator::class => SnowflakeIdGenerator::create(
                $config->snowflakeClusterId, $workerID, $config->snowflakeEpoch
            ),
        ]);
        $containerBuilder->addDefinitions(__DIR__.'/container.php');

        if ($config->environment === ServerConfigEnvironmentEnum::PRODUCTION) {
            $cacheDir = __DIR__.'/../var/cache';
            $containerBuilder->enableCompilation($cacheDir);
        }

        $container = $containerBuilder->build();
    });

$server->on("request",
    function(Request $request, Response $response) use (
        $dispatcher,
        &$container
    ) {
        $uri = $request->server['request_uri'] ?? '/';
        $method = $request->server['request_method'] ?? 'GET';

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        $uri = rawurldecode($uri);

        $response->header("Content-Type", "application/json");

        $routeInfo = $dispatcher->dispatch($method, $uri);

        if ($container === null) {
            $response->status(500);
            $response->end('{"error":"Internal Server Error","message":"Container is not ready."}');
            return;
        }
        $snowflakeGenerator = $container->get(IIntIdGenerator::class);
        $requestId = $snowflakeGenerator->generate();
        $context = Coroutine::getContext(Coroutine::getCid());
        $context['request_id'] = $requestId;
        $logger = $container->get(ILogger::class);
        $logger->setContext('request_id', $requestId);
        $logger->info(
            "Incoming request",
            [
                'method' => $method,
                'uri' => $uri,
                'dispatch_result' => $routeInfo[0],
                'route_handler' => $routeInfo[0] === Dispatcher::FOUND ? $routeInfo[1] : null,
                'client_ip' => $request->server['remote_addr'] ?? 'unknown',
                'client_port' => $request->server['remote_port'] ?? 'unknown'
            ]
        );

        switch ($routeInfo[0]) {
            case Dispatcher::FOUND:
                [$interfaceName, $methodName] = $routeInfo[1];
                $controllerInstance = $container->get($interfaceName);
                $vars = $routeInfo[2];

                $controllerInstance->$methodName($request, $response,
                    [...$vars, 'request_id' => $requestId]);
                break;

            case Dispatcher::METHOD_NOT_ALLOWED:
                $response->status(405);
                $jsonData = json_encode([
                    "error" => "Method Not Allowed",
                    "message" => "O metodo $method nao é permitido para esta rota."
                ]);
                $response->end($jsonData === false ? '{"error": "Method Not Allowed"}' : $jsonData);
                break;

            case Dispatcher::NOT_FOUND:
            default:
                $response->status(404);
                $jsonData = json_encode([
                    "error" => "Not Found",
                    "message" => "A rota '$uri' nao foi encontrada."
                ]);
                $response->end($jsonData === false ? '{"error": "Not Found"}' : $jsonData);
                break;
        }
    });

$server->start();
