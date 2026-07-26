<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Operational metrics endpoint (`/metrics`).
 */
interface IMetricsController
{
    public function get(ServerRequestInterface $request): ResponseInterface;
}
