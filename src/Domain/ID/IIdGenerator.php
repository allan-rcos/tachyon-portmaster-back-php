<?php

declare(strict_types=1);

namespace Domain\ID;

/**
 * Base contract for generating opaque, string identifiers.
 *
 * **Do not inject this interface.** It exists only so the three flavours share a
 * signature; a consumer always depends on the one that states its intent, and
 * that choice is a real constraint, not a preference:
 *
 * - {@see IDatabaseIdGenerator} — the id becomes a primary key (Snowflake, base62).
 * - {@see ISequentialIdGenerator} — the id must sort chronologically (ULID).
 * - {@see IRandomIdGenerator} — the id must be unguessable (NanoID).
 *
 * Ids are strings everywhere outside the infra layer; only infra decodes them
 * back to the column type it persists (see {@see Base62}).
 */
interface IIdGenerator
{
    public function generate(): string;
}
