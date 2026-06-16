<?php

namespace Infra\Database\Pool;

use PDO;

interface IPDOFactory
{
    public function create(): PDO;
}