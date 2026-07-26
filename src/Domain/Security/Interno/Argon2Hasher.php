<?php

declare(strict_types=1);

namespace Domain\Security\Interno;

use Domain\Security\ISecureHasher;

/**
 * Argon2id password hasher — the {@see ISecureHasher} flavour.
 *
 * Lives in the domain rather than infra: hashing a password is not an
 * integration with anything external — it is `password_hash()`, part of the
 * language runtime — and only the domain's TableModules ever use it. It is
 * reached exclusively through {@see \Domain\Interno\DomainProvider}'s private
 * factory, so nothing outside the layer can even name it.
 *
 * Each call salts independently, so two hashes of the same password differ.
 * That is what makes it safe and what makes it unusable as a key — see
 * {@see \Domain\Security\Interno\XxHasher} for the other flavour.
 */
final readonly class Argon2Hasher implements ISecureHasher
{
    public function hash(string $plain): string
    {
        return password_hash($plain, PASSWORD_ARGON2ID);
    }

    public function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }
}
