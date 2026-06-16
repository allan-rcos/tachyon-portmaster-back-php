<?php

namespace Domain\Models;

interface IRole
{
    public int $id {
        get;
    }

    public string $name {
        get;
    }

    public array $permissions {
        get;
    }
}