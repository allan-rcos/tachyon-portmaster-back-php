<?php

namespace Infra\Core;

use Domain\Ports\Core\IHasher;

class Argon2Hasher implements IHasher
{
    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}