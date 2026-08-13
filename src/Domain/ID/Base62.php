<?php

/**
 * Base62.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\ID;

use InvalidArgumentException;

/**
 * Base62 codec for identifiers.
 *
 * The domain generates a positive 63-bit Snowflake integer and encodes it to a
 * compact, URL-safe base62 string that travels across every layer and the wire.
 * Infra is the only layer that decodes it back to the integer it stores in a
 * BIGINT column ({@see \Infra\Entity\UserEntity}).
 *
 * This is why route patterns are `[A-Za-z0-9]+` and never `\d+`.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class Base62
{
    /**
     * @var string The 62 digits, in value order: digits, then uppercase, then
     *             lowercase. Changing this order invalidates every id already
     *             issued.
     */
    private const string ALPHABET =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Encodes a non-negative integer to its base62 representation.
     *
     * @param  int  $number  The value to encode; zero encodes to `"0"`.
     * @return string The base62 digits, most significant first.
     *
     * @throws InvalidArgumentException When the value is negative.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function encode(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Base62 encodes non-negative integers only.');
        }
        if ($number === 0) {
            return '0';
        }

        $encoded = '';
        while ($number > 0) {
            $encoded = self::ALPHABET[$number % 62].$encoded;
            $number = intdiv($number, 62);
        }

        return $encoded;
    }

    /**
     * Decodes a base62 string back to its integer value.
     *
     * Callers are decoding attacker-controlled path segments, so both failure
     * modes below are ordinary bad input rather than faults — catch them and
     * answer 404, do not let them escape as a 500.
     *
     * @param  string  $value  The base62 digits.
     * @return int The decoded value.
     *
     * @throws InvalidArgumentException When the string is empty, carries a
     *                                  character outside the alphabet, or
     *                                  decodes past `PHP_INT_MAX`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function decode(string $value): int
    {
        if ($value === '') {
            throw new InvalidArgumentException('Base62 cannot decode an empty string.');
        }

        $number = 0;
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $position = strpos(self::ALPHABET, $value[$i]);
            if ($position === false) {
                throw new InvalidArgumentException("Invalid base62 character: {$value[$i]}");
            }

            // Past PHP_INT_MAX the multiplication silently becomes a float and
            // the typed return throws a TypeError, which escapes as a 500. No
            // id this application issues can reach here — a Snowflake over the
            // signed range is a 2093 problem — so a value this large came from
            // the URL, and is as invalid as a character outside the alphabet.
            if ($number > intdiv(PHP_INT_MAX - $position, 62)) {
                throw new InvalidArgumentException("Base62 value out of range: {$value}");
            }

            $number = $number * 62 + $position;
        }

        return $number;
    }
}
