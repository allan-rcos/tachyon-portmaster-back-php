<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Role administration endpoints (`/roles`).
 */
interface IRoleAdminController
{
    public function list(ServerRequestInterface $request): ResponseInterface;

    public function create(ServerRequestInterface $request): ResponseInterface;

    public function syncPermissions(ServerRequestInterface $request): ResponseInterface;
}
