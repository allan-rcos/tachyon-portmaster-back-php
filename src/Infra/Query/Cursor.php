<?php

/**
 * Cursor.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Query;

/**
 * Opaque, keyset pagination cursor — owned entirely by the DQLs.
 *
 * Like a non-relational store's continuation token it carries the last seen id
 * plus the query parameters (`limit`, `search`, extra filters) it was minted
 * with. On decode, if those parameters differ from the current request's, the
 * cursor is rejected (returns null) so pagination restarts under the new filters
 * instead of silently continuing an incompatible scan. Nothing above the infra
 * layer inspects its contents; callers only pass the token through.
 *
 * Keyset, not offset: the token carries the last id seen, so a page is fetched
 * with `id > lastId` and rows inserted meanwhile cannot shift what the next page
 * returns.
 *
 * The token is encoded, not signed. It is tamper-evident only insofar as a
 * mangled token fails to decode; a caller who edits the id gets a different but
 * still valid page of data they were already entitled to read.
 *
 * @see \Infra\Query\Interno\ListProductsDQL A query that pages with one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class Cursor
{
    /**
     * @param  int  $lastId  Id of the final row of the page just served; the
     *                       next page starts strictly after it.
     * @param  array<string, scalar|null>  $filters  The parameters this cursor
     *                                               is valid for, compared
     *                                               whole on decode.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public int $lastId,
        public array $filters,
    ) {
    }

    /**
     * Renders the cursor as a URL-safe token.
     *
     * Base64url, not plain base64: `+` and `/` are swapped out and the padding
     * stripped, so the token survives a query string untouched.
     *
     * @return string The token to hand back to the caller.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function encode(): string
    {
        $json = (string) json_encode(['id' => $this->lastId, 'f' => $this->filters]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * Decodes a token, or returns null when it is malformed or was minted for a
     * different set of filters than the current request.
     *
     * Every rejection is the same null: an absent token, a token that is not
     * base64url, one whose JSON does not carry an integer id and a filter array,
     * and one whose filters no longer match. The caller treats them all as
     * "start from the beginning", which is why a caller never has to handle a
     * bad cursor as an error.
     *
     * @param  string|null  $token  As it arrived on the request; null or empty
     *                              means a first page was asked for.
     * @param  array<string, scalar|null>  $currentFilters  What this request is
     *                                                      filtering by; the
     *                                                      cursor is only
     *                                                      honoured if it was
     *                                                      minted under exactly
     *                                                      these.
     * @return ?self The cursor to continue from, or null to start over.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function decode(?string $token, array $currentFilters): ?self
    {
        if ($token === null || $token === '') {
            return null;
        }

        $json = base64_decode(strtr($token, '-_', '+/'), true);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['id'], $data['f']) || !is_int($data['id']) || !is_array($data['f'])) {
            return null;
        }

        // Filters changed since the cursor was minted → ignore it and restart.
        if ($data['f'] != $currentFilters) {
            return null;
        }

        /** @var array<string, scalar|null> $filters */
        $filters = $data['f'];

        return new self($data['id'], $filters);
    }
}
