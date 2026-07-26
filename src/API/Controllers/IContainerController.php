<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Container endpoints (`/containers`).
 */
interface IContainerController
{
    public function list(ServerRequestInterface $request): ResponseInterface;

    public function create(ServerRequestInterface $request): ResponseInterface;

    public function summary(ServerRequestInterface $request): ResponseInterface;

    public function get(ServerRequestInterface $request): ResponseInterface;

    public function update(ServerRequestInterface $request): ResponseInterface;

    public function delete(ServerRequestInterface $request): ResponseInterface;

    public function seal(ServerRequestInterface $request): ResponseInterface;

    public function dispatch(ServerRequestInterface $request): ResponseInterface;
}
