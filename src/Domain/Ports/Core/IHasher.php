<?php

namespace Domain\Ports\Core;

interface IHasher
{
    public function hash(string $password): string;

    public function verify(string $password, string $hash): bool;
}