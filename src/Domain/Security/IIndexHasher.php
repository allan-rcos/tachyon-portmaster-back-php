<?php

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
 * strings. It is emphatically **not** a password hash — it is unsalted and fast
 * by design, which is exactly what {@see ISecureHasher} must never be. Use this
 * only for values that are already unguessable on their own (a signed token, a
 * random handle), where the digest is a key and not the protection.
 */
interface IIndexHasher extends IHasher
{
}
