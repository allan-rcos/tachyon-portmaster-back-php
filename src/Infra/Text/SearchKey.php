<?php

/**
 * Search Key.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Text;

/**
 * Builds the normalized, accent-folded lowercase ASCII key that write paths
 * store in a `search_*` column so `search` filters always match regardless of
 * case or diacritics (the entity may carry columns the domain model does not).
 *
 * **Both sides must call it.** The write path stores the key and the read path
 * normalises the search term the same way; a query that filters on the raw term
 * against the normalised column silently matches nothing.
 *
 * @see \Infra\Entity\ProductEntity A write path that stores a key.
 * @see \Infra\Query\Interno\ListProductsDQL A read path that normalises the term.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final class SearchKey
{
    /**
     * Folds a value to its search key: transliterated to ASCII, lowercased, and
     * with runs of whitespace collapsed to single spaces.
     *
     * Every step degrades rather than fails — a string `iconv` cannot
     * transliterate is carried through unchanged, so a name in a script with no
     * ASCII equivalent still gets a key, just not a folded one.
     *
     * @param  string  $value  The raw name or code.
     * @return string The key to store, or to build a `LIKE` pattern from.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
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
