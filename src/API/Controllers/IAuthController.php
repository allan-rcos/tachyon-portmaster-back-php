<?php

declare(strict_types=1);

namespace API\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Authentication endpoints (`/auth/*`).
 */
interface IAuthController
{
    /**
     * `POST /setup` — creates the first user of a deployment, with a role
     * granting every registered permission, and logs them straight in (same
     * body and cookies as {@see login()}).
     *
     * Unauthenticated by necessity and answered with **409** once any user
     * exists; see {@see \App\Services\ISetupUseCase} for why that is the whole
     * of its protection.
     */
    public function setup(ServerRequestInterface $request): ResponseInterface;

    /**
     * `POST /auth/login` — verifies credentials and, on success, sets both
     * session cookies (`auth_token` + `refresh_token`) and returns the user.
     */
    public function login(ServerRequestInterface $request): ResponseInterface;

    /**
     * `POST /auth/refresh` — trades a valid refresh cookie for a fresh access
     * token, rotating the refresh token in the process. Answers 204.
     */
    public function refresh(ServerRequestInterface $request): ResponseInterface;

    /**
     * `POST /auth/logout` — revokes the refresh token and clears both cookies.
     * Answers 204.
     */
    public function logout(ServerRequestInterface $request): ResponseInterface;
}
