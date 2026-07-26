<?php

declare(strict_types=1);

namespace Domain\Models;

/**
 * A boolean flag stored against the digest of a value, inside an
 * {@see IMarkerGroup}.
 *
 * The point is to answer one question — *did we issue this, and is it still
 * live?* — without keeping the value itself. The caller hands over a plain
 * string; {@see \Domain\TableModules\IMarkerTM} is the only thing that sees it,
 * hashing it with {@see \Domain\Security\IIndexHasher} into the {@see $key} that
 * gets stored. Nothing downstream can recover the original, and nothing needs
 * to: a lookup recomputes the digest and matches on it.
 *
 * A marker is therefore **not** a secret store and not an audit trail. It holds
 * one bit, and it is only meaningful for values that are already unguessable on
 * their own — a signed token, a random handle. Flagging a guessable value here
 * would let anyone probe for it.
 *
 * The flag's transitions carry the meaning; see
 * {@see \App\Services\ISetMarkerUseCase}, which owns them.
 */
interface IMarker
{
    /** Slug of the {@see IMarkerGroup} this flag belongs to. */
    public string $group {
        get;
    }

    /** Digest of the value being flagged — never the value itself. */
    public string $key {
        get;
    }

    /**
     * The flag. A marker is born `true` ("issued and live") and is consumed by
     * being set to `false`; it is never deleted, because the difference between
     * "consumed" and "never existed" is what makes a replay detectable until the
     * TTL expires.
     */
    public bool $flag {
        get;
    }
}
