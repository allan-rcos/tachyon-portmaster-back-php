<?php

declare(strict_types=1);

/**
 * Main application entry point.
 *
 * Boots the OpenSwoole HTTP server and, on each worker start, materialises the
 * whole object graph through the hand-wired register chain
 * ({@see \API\ApiRegister} → app → { infra → domain }) — no DI container. The
 * per-worker id parameterises the Snowflake generator; the assembled provider
 * hands back a ready PSR-15 handler that drives every request.
 *
 * The one thing built *outside* that per-worker graph is
 * {@see \Infra\OpenSwooleExtension}, attached before `$server->start()`: shared
 * memory has to be allocated while there is still a single process to allocate
 * it in.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */

require_once __DIR__.'/../../vendor/autoload.php';

use API\ApiRegister;
use API\Bootstrap\DotEnvStarter;
use Infra\Logging\MonologFactory;
use Infra\OpenSwooleExtension;
use API\Http\ResponseEmitter;
use OpenSwoole\Core\Psr\ServerRequest;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use OpenSwoole\Http\Server;
use OpenSwoole\Runtime;
use Psr\Http\Server\RequestHandlerInterface;
use Shared\Exceptions\LeafContext;

Runtime::enableCoroutine();

// Every datetime in this system is UTC; see docs/database.md. Set here as well
// as in the image's php.ini, so a run outside the image behaves the same.
date_default_timezone_set('UTC');

// Composition root: read the environment into the per-layer config VOs.
$config = DotEnvStarter::startFromEnv(__DIR__.'/../..');
if ($config instanceof LeafContext) {
    echo "Error loading environment variables: $config->message\n";
    foreach (($config->details?->toArray() ?? []) as $key => $error) {
        echo "- $key: $error\n";
    }
    exit(1);
}

// Bootstrap logger for server lifecycle events (the request path uses the
// provider's logger, built per worker).
$logger = MonologFactory::create(level: $config->log->level);

/** @var RequestHandlerInterface|null $handler */
$handler = null;

$server = new Server($config->api->host, $config->api->port);
$server->set([
    'worker_num'       => $config->api->workerNum,
    'enable_coroutine' => true,
]);

// Shared memory is allocated here because `$server->start()` below forks.
$extension = OpenSwooleExtension::attach($server, $config->cache, $config->log);

$server->on('start', function (Server $server) use (&$logger): void {
    $logger->info('Swoole API Stack [OpenSwoole\\Core PSR] started', [
        'host' => $server->host,
        'port' => $server->port,
    ]);
});

$server->on('WorkerStart', function (Server $server, int $workerID) use (&$handler, $config, $extension): void {
    // Materialise the object graph for this worker; the worker id is the
    // Snowflake server id. The extension is the one collaborator that predates
    // the fork, so every worker's graph is wired to the same shared memory.
    $handler = ApiRegister::execute($config, $workerID, $extension)->router();
});

$server->on('request', function (Request $request, Response $response) use (&$handler, &$logger): void {
    if (!$handler instanceof RequestHandlerInterface) {
        $response->status(500);
        $response->header('Content-Type', 'application/json');
        $response->end('{"error":"Internal Server Error","message":"Handler is not ready."}');
        return;
    }

    // A body-less POST/PUT/DELETE arrives with `Content-Length: 0`, and
    // OpenSwoole's PSR layer rejects it: its header guard is
    // `$value !== '' && empty($value)`, and `empty('0')` is true in PHP. The
    // header carries nothing the PSR request needs — the body is read from
    // rawContent() — so dropping it is the difference between a working
    // endpoint and a dead worker.
    if (($request->header['content-length'] ?? null) === '0') {
        unset($request->header['content-length']);
    }

    try {
        $psrRequest = ServerRequest::from($request);
    } catch (Throwable $e) {
        // This runs *before* the PSR stack, so RecovererMiddleware cannot cover
        // it: an exception escaping here kills the worker and leaves the client
        // waiting on a response that will never come. Four such requests take
        // down a four-worker server.
        $logger->error('Malformed request rejected before the PSR stack', [
            'error' => $e->getMessage(),
            'uri' => $request->server['request_uri'] ?? '',
        ]);
        $response->status(400);
        $response->header('Content-Type', 'application/json');
        $response->end('{"error":"Bad Request","message":"The request could not be parsed."}');
        return;
    }

    $psrResponse = $handler->handle($psrRequest);

    ResponseEmitter::emit($response, $psrResponse);
});

$server->start();
