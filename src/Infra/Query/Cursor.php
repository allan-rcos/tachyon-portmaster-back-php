<?php

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
 */
final readonly class Cursor
{
    /**
     * @param  array<string, scalar|null>  $filters  The parameters this cursor is valid for.
     */
    public function __construct(
        public int $lastId,
        public array $filters,
    ) {
    }

    /**
     * URL-safe token encoding the last id and the filters it belongs to.
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
     * @param  array<string, scalar|null>  $currentFilters
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
