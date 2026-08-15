<?php

/**
 * Marker Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Models;

/**
 * A boolean flag stored against the digest of a value, inside an
 * {@see IMarkerGroup}.
 *
 * It answers one question — *did we issue this, and is it still live?* — without
 * keeping the value itself. The caller hands over a plain string;
 * {@see \Domain\TableModules\IMarkerTM} is the only thing that sees it, hashing
 * it with {@see \Domain\Security\IIndexHasher} into the {@see $key} that gets
 * stored. Nothing downstream can recover the original, and nothing needs to: a
 * lookup recomputes the digest and matches on it.
 *
 * A marker is therefore **not** a secret store and not an audit trail. It holds
 * one bit, and is only meaningful for values already unguessable on their own —
 * a signed token, a random handle. Flagging a guessable value here would let
 * anyone probe for it.
 *
 * @see \App\Services\ISetMarkerUseCase Owns the flag's transitions.
 * @see \Infra\Repository\IMarkerRepository Where these are stored.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Why storage is RAM, and what that costs.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IMarker
{
    /**
     * @var string Slug of the {@see IMarkerGroup} this flag belongs to.
     */
    public string $group {
        get;
    }

    /**
     * @var string Digest of the value being flagged — never the value itself.
     */
    public string $key {
        get;
    }

    /**
     * The flag. A marker is born `true` ("issued and live") and is consumed by
     * being set to `false`; it is never deleted, because the difference between
     * "consumed" and "never existed" is what makes a replay detectable until the
     * TTL expires.
     *
     * @var bool True while live, false once consumed.
     */
    public bool $flag {
        get;
    }
}
