<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Self-service account endpoints (`/account`) — always operate on the caller.
 */
interface IAccountController
{
    public function get(ServerRequestInterface $request): ResponseInterface;

    public function update(ServerRequestInterface $request): ResponseInterface;

    public function changePassword(ServerRequestInterface $request): ResponseInterface;
}
