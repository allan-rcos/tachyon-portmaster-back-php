<?php

/**
 * Secure Hasher Contract.
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
 * Hashes a **secret** — today, only user passwords.
 *
 * Implemented by {@see \Domain\Security\Interno\Argon2Hasher}. Salted, so the
 * same password hashes differently every time, and deliberately slow, so a
 * leaked digest is expensive to attack offline. Both properties are the point,
 * and both make it useless as a lookup key: {@see IHasher::hash()} is not
 * reproducible here, so the only way to match a value is
 * {@see IHasher::verify()} against a digest you already hold.
 * {@see IIndexHasher} is the flavour for keys.
 *
 * @see IHasher The shared signature, and how to choose between the flavours.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface ISecureHasher extends IHasher
{
}
