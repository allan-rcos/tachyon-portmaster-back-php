<?php

namespace Domain\Models;

interface IUser
{
    public string $id {
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

    /** @var list<IRole> */
    public array $roles {
        get;
    }
}