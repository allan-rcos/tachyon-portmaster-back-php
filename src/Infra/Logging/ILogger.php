<?php

declare(strict_types=1);

namespace Infra\Logging;

interface ILogger
{
    /** @param array<string, mixed> $context */
    public function debug(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function info(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function warn(string $message, array $context = []): void;

    /** @param array<string, mixed> $context */
    public function error(string $message, array $context = []): void;

    public function withChannel(string $name): ILogger;

    public function setContext(string $key, string $value): void;
}
