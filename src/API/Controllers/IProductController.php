<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Product catalogue endpoints (`/products`).
 */
interface IProductController
{
    public function list(ServerRequestInterface $request): ResponseInterface;

    public function create(ServerRequestInterface $request): ResponseInterface;

    public function get(ServerRequestInterface $request): ResponseInterface;

    public function update(ServerRequestInterface $request): ResponseInterface;

    public function delete(ServerRequestInterface $request): ResponseInterface;
}
