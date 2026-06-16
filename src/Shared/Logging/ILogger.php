<?php

namespace Shared\Logging;

interface ILogger
{
    public function debug(string $message, array $context = []): void;

    public function info(string $message, array $context = []): void;

    public function warn(string $message, array $context = []): void;

    public function error(string $message, array $context = []): void;

    public function withChannel(string $name): ILogger;

    public function setContext(string $key, string $value): void;
}