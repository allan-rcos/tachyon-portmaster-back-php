<?php

declare(strict_types=1);

namespace API\Bootstrap;

use BackedEnum;
use Ds\Map;

/**
 * Typed, validating reader over the loaded environment.
 *
 * Each chain link asks for the variables it cares about and gets either a usable
 * value or the declared default; anything malformed is recorded here rather than
 * thrown, so one bad variable does not hide the next five. The accumulated
 * errors are reported together by {@see DotEnvStarter}.
 */
final class EnvSource
{
    /** @var Map<string, string> */
    private Map $errors;

    public function __construct()
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();
        $this->errors = $errors;
    }

    public function string(DotEnvVariables $key, string $default): string
    {
        $value = $this->raw($key);

        return $value ?? $default;
    }

    public function int(DotEnvVariables $key, int $default): int
    {
        $value = $this->raw($key);

        if ($value === null) {
            return $default;
        }

        if (!is_numeric($value)) {
            $this->errors->put($key->value, 'Must be a number');

            return $default;
        }

        return (int) $value;
    }

    public function float(DotEnvVariables $key, float $default): float
    {
        $value = $this->raw($key);

        if ($value === null) {
            return $default;
        }

        if (!is_numeric($value)) {
            $this->errors->put($key->value, 'Must be a number');

            return $default;
        }

        return (float) $value;
    }

    public function bool(DotEnvVariables $key, bool $default): bool
    {
        $value = $this->raw($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enumClass
     * @param  T  $default
     * @return T
     */
    public function enum(DotEnvVariables $key, string $enumClass, BackedEnum $default): BackedEnum
    {
        $value = $this->raw($key);

        if ($value === null) {
            return $default;
        }

        $resolved = $enumClass::tryFrom($value);

        if ($resolved === null) {
            $cases = array_map(static fn (BackedEnum $case): string => (string) $case->value, $enumClass::cases());
            $this->errors->put($key->value, 'Must be one of: '.implode(', ', $cases));

            return $default;
        }

        return $resolved;
    }

    public function hasErrors(): bool
    {
        return !$this->errors->isEmpty();
    }

    /**
     * @return Map<string, string>
     */
    public function errors(): Map
    {
        return $this->errors;
    }

    /**
     * The raw value, or null when unset or empty — an empty variable is treated
     * as absent so a blank line in `.env` falls back to the default instead of
     * producing an empty host or a zero port.
     */
    private function raw(DotEnvVariables $key): ?string
    {
        $value = $_ENV[$key->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
