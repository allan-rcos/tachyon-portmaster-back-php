<?php

/**
 * Coerces JSON Trait.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Fbs\Contracts;

/**
 * Scalar/array coercion helpers for decoding untyped JSON structures.
 *
 * {@see json_decode()} yields `mixed` for every value, so hydrating a proxy's
 * strictly-typed fields from a decoded body would otherwise scatter `is_*`
 * guards and casts across every {@see IFbsProxy::jsonUnserialize()}. These
 * helpers narrow a single key once, in one place.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
trait CoercesJson
{
    /**
     * @param  array<string, mixed>  $data
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonNullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonString(array $data, string $key): string
    {
        return self::jsonNullableString($data, $key) ?? '';
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonFloat(array $data, string $key): float
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonBool(array $data, string $key): bool
    {
        return (bool) ($data[$key] ?? false);
    }

    /**
     * The value at `$key` as an associative structure ready to be fed to a
     * child proxy's {@see IFbsProxy::jsonUnserialize()}, or null when absent.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonObject(array $data, string $key): ?array
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
     * to a child proxy's {@see IFbsProxy::jsonUnserialize()}.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonRows(array $data, string $key): array
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
     * @param  array<string, mixed>  $data
     * @return list<string>
     *
     * @copyright 2026 Tachyon
     */
    protected static function jsonStringList(array $data, string $key): array
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
