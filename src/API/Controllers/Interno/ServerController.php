<?php

/**
 * Server Controller Implementation Module.
 *
 * A module containing the concrete implementation of server queries.
 *
 * @category Api\Controllers\Interno
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

namespace API\Controllers\Interno;

use API\Controllers\IServerController;
use API\DTO\ProjectInfoDTO;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use Shared\Logging\ILogger;

/**
 * Internal controller implementation for server-related endpoints.
 *
 * Responsible for returning structured data related strictly to the server
 * internals status and properties.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @see IServerController
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
class ServerController implements IServerController
{
    public function __construct(
        private ILogger $logger
    ) {
        $this->logger = $this->logger->withChannel("ServerController");
    }

    /**
     * @inheritDoc
     */
    public function getInfo(
        Request $request,
        Response $response,
        array $vars = []
    ): void {
        $dto = new ProjectInfoDTO(
            name: "allan/swoole-api-stack",
            version: "0.0.1",
            environment: "development",
            runtime: "PHP ".PHP_VERSION." + OpenSwoole",
            memory_usage_mb: round(memory_get_usage() / 1024 / 1024, 2)
        );

        $json = json_encode($dto);
        if ($json === false) {
            $this->logger->error(
                "Failed to serialize project info DTO",
                ['error' => json_last_error_msg()]
            );
            $response->status(500);
            $response->end('{"error": "Internal Server Error"}');
            return;
        }

        $this->logger->debug(
            "Project info retrieved",
            $dto->jsonSerialize()
        );
        $response->status(200);
        $response->end($json);
    }
}