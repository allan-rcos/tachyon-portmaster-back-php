<?php

/**
 * DotEnv Starter Module.
 *
 * Module structured to load dot-env rules mapping.
 *
 * @category Infra\Core
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @filesource
 */

namespace Infra\Core;

use Dotenv\Dotenv;
use Ds\Map;
use Shared\Config\ServerConfig;
use Shared\Config\ServerConfigEnvironmentEnum;
use Shared\Config\ServerConfigLogLevel;
use Shared\Exceptions\LeafContext;

/**
 * Class responsible for loading and validating environment configurations.
 *
 * It acts like a bootstrapper setting the ground properties map from process parameters context values loading mapping.
 *
 * @license {@link https://www.gnu.org/licenses/gpl-3.0.pt-br.html GPL-3}
 * @copyright 2026 Ricardo Állan Costa
 * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
 *
 * @since 0.0.1 File creation.
 *
 * @version 0.0.1
 */
class DotEnvStarter
{
    private Map $errors;
    private ?string $host;
    private ?int $port;
    private ?int $snowflakeEpoch;
    private ?int $snowflakeMachineId;
    private ?int $workerNum;
    private ?ServerConfigEnvironmentEnum $environment;
    private ?ServerConfigLogLevel $logLevel;

    private function __construct()
    {
        $this->errors = new Map();
    }

    /**
     * Loads environment variables from the given directory and constructs server config or returns errors.
     *
     * @param  string  $dir  The directory containing the .env file.
     * @return LeafContext|ServerConfig Returns ServerConfig on success or a LeafContext with error details on failure.
     * @since 0.0.1 File creation.
     *
     * @version 0.0.1
     *
     * @author Ricardo Állan Costa <ricardoallancosta@hotmail.com>
     * @copyright 2026 Ricardo Állan Costa
     *
     */
    static function start_from_env(string $dir): LeafContext|ServerConfig
    {
        $dotenv = Dotenv::createImmutable($dir);
        $dotenv->load();

        $variables = new self();
        $variables->setHost($_ENV[DotEnvVariables::APP_HOST->value] ?? null)
            ->setPort($_ENV[DotEnvVariables::APP_PORT->value] ?? null)
            ->setSnowflakeEpoch($_ENV[DotEnvVariables::SNOWFLAKE_EPOCH->value] ?? null)
            ->setSnowflakeMachineId($_ENV[DotEnvVariables::SNOWFLAKE_MACHINE_ID->value] ?? null)
            ->setWorkerNum($_ENV[DotEnvVariables::APP_WORKER_NUM->value] ?? null)
            ->setEnvironment($_ENV[DotEnvVariables::ENVIRONMENT->value] ?? null)
            ->setLogLevel($_ENV[DotEnvVariables::LOG_LEVEL->value] ?? null);

        if ($variables->hasErrors()) {
            return $variables->leafContext();
        }

        return $variables->config();
    }

    private function setLogLevel(?string $logLevel): static
    {
        $this->logLevel = $this->validateEnum(
            $logLevel,
            DotEnvVariables::LOG_LEVEL->value,
            ServerConfigLogLevel::class
        );
        return $this;
    }

    /**
     * @template T
     */
    private function validateEnum(
        ?string $value,
        string $variableName,
        mixed $enumClass
    ): mixed {
        if ($value === null) {
            return null;
        }
        /** @var T|null $enumValue */
        $enumValue = $enumClass::tryFrom($value);
        if (null === $enumValue) {
            $cases = array_map(fn($case) => $case->value, $enumClass::cases());
            $this->errors->put($variableName,
                'Must be one of: '.implode(', ', $cases));
            return null;
        }
        return $enumValue;
    }

    private function setEnvironment(?string $environment
    ): static {
        $this->environment = $this->validateEnum(
            $environment,
            DotEnvVariables::ENVIRONMENT->value,
            ServerConfigEnvironmentEnum::class
        );
        return $this;
    }

    private function setWorkerNum(?string $workerNum): static
    {
        $this->workerNum = $this->validateInteger($workerNum,
            DotEnvVariables::APP_WORKER_NUM->value);
        return $this;
    }

    private function validateInteger(?string $value, string $variableName): ?int
    {
        if ($value != null && !is_numeric($value)) {
            $this->errors->put($variableName, 'Must be a number');
            return null;
        }
        return (int) $value;
    }

    private function setSnowflakeMachineId(?string $snowflakeMachineId): static
    {
        $this->snowflakeMachineId = $this->validateInteger($snowflakeMachineId,
            DotEnvVariables::SNOWFLAKE_MACHINE_ID->value);
        return $this;
    }

    private function setSnowflakeEpoch(?string $snowflakeEpoch): static
    {
        $this->snowflakeEpoch = $this->validateInteger($snowflakeEpoch,
            DotEnvVariables::SNOWFLAKE_EPOCH->value);
        return $this;
    }

    private function setPort(?string $port): static
    {
        $this->port = $this->validateInteger($port,
            DotEnvVariables::APP_PORT->value);
        return $this;
    }

    private function setHost(?string $host): static
    {
        $this->host = $host;
        return $this;
    }

    private function hasErrors(): bool
    {
        return $this->errors->count() > 0;
    }

    private function leafContext(): LeafContext
    {
        return new LeafContext(
            message: 'Invalid environment variables',
            details: $this->errors,
        );
    }

    private function config(): ServerConfig
    {
        return ServerConfig::new(
            host: $this->host,
            port: $this->port,
            snowflakeEpoch: $this->snowflakeEpoch,
            snowflakeClusterId: $this->snowflakeMachineId,
            workerNum: $this->workerNum,
            environment: $this->environment,
            logLevel: $this->logLevel,
        );
    }
}