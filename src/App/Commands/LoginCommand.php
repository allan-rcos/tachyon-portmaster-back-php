<?php

declare(strict_types=1);

namespace App\Commands;

/**
 * Input for {@see \App\Services\ILoginUseCase}: the credentials to authenticate.
 *
 * Commands are plain DTOs — they carry the use case's input across the
 * controller boundary so the use case never sees an HTTP request or a proxy.
 */
final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
