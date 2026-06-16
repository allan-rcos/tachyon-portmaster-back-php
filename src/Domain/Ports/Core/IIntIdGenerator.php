<?php

namespace Domain\Ports\Core;

interface IIntIdGenerator
{
    public function generate(): int;
}