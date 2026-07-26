<?php

declare(strict_types=1);

namespace Infra\Text;

/**
 * Builds the normalized, accent-folded lowercase ASCII key that write paths
 * store in a `search_*` column so `search` filters always match regardless of
 * case or diacritics (the entity may carry columns the domain model does not).
 */
final class SearchKey
{
    public static function of(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii === false) {
            $ascii = $value;
        }

        $lower = strtolower($ascii);
        $collapsed = preg_replace('/\s+/', ' ', $lower);

        return trim($collapsed ?? $lower);
    }
}
