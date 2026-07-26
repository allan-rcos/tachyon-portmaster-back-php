<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * User administration endpoints (`/users`).
 */
interface IUserAdminController
{
    public function list(ServerRequestInterface $request): ResponseInterface;

    public function create(ServerRequestInterface $request): ResponseInterface;

    public function get(ServerRequestInterface $request): ResponseInterface;

    public function update(ServerRequestInterface $request): ResponseInterface;

    public function delete(ServerRequestInterface $request): ResponseInterface;

    public function resetPassword(ServerRequestInterface $request): ResponseInterface;

    public function updateRoles(ServerRequestInterface $request): ResponseInterface;
}
