<?php

namespace Domain\Models;

interface IUser
{
    public int $id {
        get;
    }

    public string $name {
        get;
    }

    public string $email {
        get;
    }

    public string $passwordHash {
        get;
    }

    public array $roles {
        get;
    }
}