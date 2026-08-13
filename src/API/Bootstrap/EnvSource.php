<?php

/**
 * Env Source.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * A variable that is absent, empty or malformed all resolve to the caller's
 * default; only the malformed case is also recorded as an error. So a boot that
 * reports nothing still ran on defaults wherever a variable was missing.
 *
 * @see DotEnvVariables The variables that may be asked for.
 * @see \API\Bootstrap\Chain\DotEnvChain The links that do the asking.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class EnvSource
{
    /**
     * @var Map<string, string> Variable name to the reason it was rejected.
     */
    private Map $errors;

    /**
     * Starts with an empty error set; the environment is read lazily, per key.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct()
    {
        /** @var Map<string, string> $errors */
        $errors = new Map();
        $this->errors = $errors;
    }

    /**
     * A variable as a string.
     *
     * Cannot fail: any set, non-empty value is already one.
     *
     * @param  DotEnvVariables  $key  Variable to read.
     * @param  string  $default  Value to use when it is unset or empty.
     * @return string The value, or the default.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function string(DotEnvVariables $key, string $default): string
    {
        $value = $this->raw($key);

        return $value ?? $default;
    }

    /**
     * A variable as an integer.
     *
     * @param  DotEnvVariables  $key  Variable to read.
     * @param  int  $default  Value to use when it is unset, empty or not numeric.
     * @return int The value, or the default — with a recorded error in the
     *             not-numeric case.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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

    /**
     * A variable as a float.
     *
     * @param  DotEnvVariables  $key  Variable to read.
     * @param  float  $default  Value to use when it is unset, empty or not
     *                          numeric.
     * @return float The value, or the default — with a recorded error in the
     *               not-numeric case.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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

    /**
     * A variable as a boolean.
     *
     * Never records an error: `1`, `true`, `yes` and `on` (in any case) are
     * true, and **everything else** — including a typo — is false.
     *
     * @param  DotEnvVariables  $key  Variable to read.
     * @param  bool  $default  Value to use when it is unset or empty.
     * @return bool The value, or the default.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function bool(DotEnvVariables $key, bool $default): bool
    {
        $value = $this->raw($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * A variable as a case of a backed enum.
     *
     * An unrecognised value is reported with the accepted cases listed, since
     * the whole point of an enum-typed variable is that the valid set is small
     * and knowable.
     *
     * @template T of BackedEnum
     *
     * @param  DotEnvVariables  $key  Variable to read.
     * @param  class-string<T>  $enumClass  Enum to resolve the value against.
     * @param  T  $default  Case to use when it is unset, empty or unrecognised.
     * @return T The matching case, or the default — with a recorded error in
     *           the unrecognised case.
     *
     * @copyright 2026 Tachyon
     *
     * @api
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

    /**
     * Whether any variable was rejected while the chain ran.
     *
     * @return bool True when at least one error was recorded.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function hasErrors(): bool
    {
        return !$this->errors->isEmpty();
    }

    /**
     * Every rejection recorded so far.
     *
     * @return Map<string, string> Variable name to the reason, ready to become
     *                             a {@see \Shared\Exceptions\LeafContext}'s
     *                             details.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function errors(): Map
    {
        return $this->errors;
    }

    /**
     * The raw value behind a key.
     *
     * An empty variable is treated as absent, so a blank line in `.env` falls
     * back to the default instead of producing an empty host or a zero port.
     *
     * @param  DotEnvVariables  $key  Variable to read.
     * @return string|null The value, or null when unset or empty.
     *
     * @copyright 2026 Tachyon
     */
    private function raw(DotEnvVariables $key): ?string
    {
        $value = $_ENV[$key->value] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
