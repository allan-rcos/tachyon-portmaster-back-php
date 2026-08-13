<?php

/**
 * Hasher Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Security;

/**
 * Base contract for turning a plain string into a digest that can be checked
 * but not read back.
 *
 * **Do not inject this interface.** Like {@see \Domain\ID\IIdGenerator}, it
 * exists only so the flavours share a signature; a consumer always depends on
 * the one that states its intent, and that choice is a real constraint rather
 * than a preference:
 *
 * - {@see ISecureHasher} — the digest guards a **secret** (a password). Salted
 *   and deliberately slow, so it is not reproducible and can never be a key.
 * - {@see IIndexHasher} — the digest *is* a **key**. Deterministic and fast, so
 *   it can be recomputed and looked up, which means it must not guard a secret.
 *
 * Picking the wrong one breaks in both directions: a password behind a fast
 * deterministic hash is a rainbow-table target, and a lookup key behind a salted
 * hash can simply never be found again.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IHasher
{
    /**
     * Digests a value.
     *
     * @param  string  $plain  The value to digest.
     * @return string The digest. Reproducible or not depending on the flavour —
     *                see the interface you injected.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function hash(string $plain): string;

    /**
     * Whether a value is the one behind a digest.
     *
     * @param  string  $plain  The candidate value.
     * @param  string  $hash  A digest previously produced by {@see hash()}.
     * @return bool True when they match.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function verify(string $plain, string $hash): bool;
}
