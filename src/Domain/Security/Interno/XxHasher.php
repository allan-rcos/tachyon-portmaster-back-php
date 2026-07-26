<?php

declare(strict_types=1);

namespace Domain\Security\Interno;

use Domain\Security\IIndexHasher;

/**
 * {@see IIndexHasher} over xxHash64.
 *
 * xxh64 is a non-cryptographic hash, and that is the right trade here: the
 * values it digests are already unguessable (signed tokens, random handles), so
 * the digest is doing indexing work, not protection work. What it buys is a
 * 16-character key that is cheap to compute on every request and cheap for the
 * database to index — where a cryptographic hash would cost far more for a
 * property nothing is relying on.
 *
 * `xxh64` ships with PHP 8.1+ in the bundled hash extension, so this needs no
 * PECL dependency.
 */
final readonly class XxHasher implements IIndexHasher
{
    private const string ALGORITHM = 'xxh64';

    public function hash(string $plain): string
    {
        return hash(self::ALGORITHM, $plain);
    }

    public function verify(string $plain, string $hash): bool
    {
        // The digest is reproducible, so this is a plain comparison — but a
        // timing-safe one, since the caller may be checking a value an attacker
        // controls.
        return hash_equals($hash, $this->hash($plain));
    }
}
