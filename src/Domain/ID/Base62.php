<?php

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
 */
final class Base62
{
    private const string ALPHABET =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /**
     * Encodes a non-negative integer to its base62 representation.
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
     * @throws InvalidArgumentException when the string carries a non-base62 character.
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
