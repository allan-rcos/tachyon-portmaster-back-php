<?php

/**
 * Index Hasher Contract.
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
 * Hashes a value into a **lookup key**.
 *
 * Implemented by {@see \Domain\Security\Interno\XxHasher}. Deterministic, so the
 * same input always yields the same key and a row can be found again by
 * recomputing it; short and fast, so it is cheap to index and cheap to check on
 * every request.
 *
 * What it buys is not secrecy but *indirection*: the stored key cannot be read
 * back as the original value, so a dump of the table hands out no usable
 * strings. It is emphatically **not** a password hash — unsalted and fast by
 * design, which is exactly what {@see ISecureHasher} must never be. Use it only
 * for values already unguessable on their own (a signed token, a random
 * handle), where the digest is a key and not the protection.
 *
 * @see IHasher The shared signature, and how to choose between the flavours.
 * @see \Domain\Models\IMarker The main consumer — flags stored against a digest.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IIndexHasher extends IHasher
{
}
