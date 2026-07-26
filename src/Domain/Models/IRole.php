<?php

namespace Domain\Models;

interface IRole
{
    public string $id {
        get;
    }

    public string $name {
        get;
    }

    /** @var list<string> */
    public array $permissions {
        get;
    }
}