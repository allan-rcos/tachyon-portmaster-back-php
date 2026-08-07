<?php

/**
 * JSON Helper.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\Interno;

use API\Negociation\IRequestAbstractFactory;

/**
 * Scalar/array coercion helpers for decoding untyped JSON structures.
 *
 * {@see json_decode()} yields `mixed` for every value, so hydrating a DTO's
 * strictly-typed fields from a decoded body would otherwise scatter `is_*`
 * guards and casts across every {@see IRequestAbstractFactory::fromJson()}.
 * These helpers narrow a single key once, in one place.
 *
 * A class of static functions rather than a trait: none of this is behaviour a
 * factory *is*, it is a toolbox a factory *uses*, and a caller reading
 * `JsonHelper::nullableString(...)` can find the code without knowing which
 * traits the class happens to compose.
 *
 * @see IRequestAbstractFactory The contract whose `fromJson()` this serves.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class JsonHelper
{
    /**
     * The value at `$key` as a string, or null when absent or not scalar.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return string|null The narrowed value.
     *
     * @copyright 2026 Tachyon
     */
    public static function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * The value at `$key` as a string, empty when absent.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return string The narrowed value.
     *
     * @copyright 2026 Tachyon
     */
    public static function string(array $data, string $key): string
    {
        return self::nullableString($data, $key) ?? '';
    }

    /**
     * The value at `$key` as an int, zero when absent or not numeric.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return int The narrowed value.
     *
     * @copyright 2026 Tachyon
     */
    public static function int(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * The value at `$key` as a float, zero when absent or not numeric.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return float The narrowed value.
     *
     * @copyright 2026 Tachyon
     */
    public static function float(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * The value at `$key` as a bool, false when absent.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return bool The narrowed value.
     *
     * @copyright 2026 Tachyon
     */
    public static function bool(array $data, string $key): bool
    {
        return (bool) ($data[$key] ?? false);
    }

    /**
     * The value at `$key` as an associative structure ready to be fed to a
     * child factory's {@see IRequestAbstractFactory::fromJson()}, or null when
     * absent.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return array<string, mixed>|null The narrowed value.
     *
     * @copyright 2026 Tachyon
     */
    public static function object(array $data, string $key): ?array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return null;
        }

        $out = [];
        foreach ($value as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    /**
     * The value at `$key` as a list of associative rows, each ready to be fed
     * to a child factory's {@see IRequestAbstractFactory::fromJson()}.
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return list<array<string, mixed>> The narrowed rows.
     *
     * @copyright 2026 Tachyon
     */
    public static function rows(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $row) {
            if (is_array($row)) {
                /** @var array<string, mixed> $row */
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * The value at `$key` as a list of strings (a scalar vector field).
     *
     * @param  array<string, mixed>  $data  A decoded JSON structure.
     * @param  string  $key  The schema field name.
     * @return list<string> The narrowed values.
     *
     * @copyright 2026 Tachyon
     */
    public static function stringList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $items = [];
        foreach ($value as $item) {
            if (is_scalar($item)) {
                $items[] = (string) $item;
            }
        }

        return $items;
    }
}
