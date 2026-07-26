<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Manifest (cargo) endpoints (`/manifests`).
 */
interface IManifestController
{
    public function loadItem(ServerRequestInterface $request): ResponseInterface;

    public function unloadItem(ServerRequestInterface $request): ResponseInterface;
}
