<?php

namespace Shared\Logging;

use Monolog\Logger;
use OpenSwoole\Coroutine;

readonly class MonologAdapter implements ILogger
{
    public function __construct(
        private Logger $monolog
    ) {
    }

    /**
     * @inheritDoc
     */
    public function debug(string $message, array $context = []): void
    {
        $this->monolog->debug($message,
            $this->appendCoroutineContext($context));
    }

    /**
     * Captura os metadados isolados da corrotina atual e mescla no log.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function appendCoroutineContext(array $context): array
    {
        $coroutineId = Coroutine::getCid();
        if (!extension_loaded('openswoole') || $coroutineId === -1) {
            return $context;
        }

        $coroutineContext = Coroutine::getContext($coroutineId);

        if (isset($coroutineContext['log_metadata']) && is_array($coroutineContext['log_metadata'])) {
            return array_merge($coroutineContext['log_metadata'], $context);
        }

        return $context;
    }

    /**
     * @inheritDoc
     */
    public function info(string $message, array $context = []): void
    {
        $this->monolog->info($message, $this->appendCoroutineContext($context));
    }

    /**
     * @inheritDoc
     */
    public function error(string $message, array $context = []): void
    {
        $this->monolog->error($message,
            $this->appendCoroutineContext($context));
    }

    public function withChannel(string $name): ILogger
    {
        return new self($this->monolog->withName($name));
    }

    public function setContext(string $key, string $value): void
    {
        $coroutineId = Coroutine::getCid();
        if (!extension_loaded('openswoole') || $coroutineId === -1) {
            $this->warn(
                "openswoole extension not loaded, failed to add context",
                ['key' => $key, 'value' => $value],
            );
            return;
        }

        $coroutineContext = Coroutine::getContext($coroutineId);

        $coroutineContext['log_metadata'] = array_merge($coroutineContext['log_metadata'] ?? [],
            [$key => $value]);
    }

    /**
     * @inheritDoc
     */
    public function warn(string $message, array $context = []): void
    {
        $this->monolog->warning($message,
            $this->appendCoroutineContext($context));
    }
}