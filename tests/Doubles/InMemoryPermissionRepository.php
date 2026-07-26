<?php

declare(strict_types=1);

namespace Tests\Doubles;

use Domain\Models\IPermission;
use Ds\Seq;
use Infra\Repository\IPermissionRepository;
use Shared\Exceptions\Result;

/**
 * An {@see IPermissionRepository} that keeps the catalogue in a plain array.
 *
 * The real registry ({@see \Infra\Repository\Interno\PermissionRegistry}) is a
 * `ENGINE=MEMORY` table now, so it needs a live MariaDB — and the tests that use
 * this double are not about storage: they pin that a use case declares its
 * permission at construction and refuses a caller who lacks it. Registering
 * against the database is covered end-to-end by the Go integration suite, where
 * every permission-gated endpoint exercises it for real.
 *
 * Behaviour matches the registry's contract: indices start at 1 (0 means "not
 * registered") and registration is idempotent by slug.
 */
final class InMemoryPermissionRepository implements IPermissionRepository
{
    /** @var array<string, IPermission> */
    private array $bySlug = [];

    public function add(IPermission $permission): Result
    {
        $existing = $this->bySlug[$permission->slug] ?? null;

        if ($existing !== null) {
            return Result::success($existing);
        }

        $registered = $permission->withId(count($this->bySlug) + 1);
        $this->bySlug[$permission->slug] = $registered;

        return Result::success($registered);
    }

    public function getBySlug(string $slug): ?IPermission
    {
        return $this->bySlug[$slug] ?? null;
    }

    public function getById(int $id): ?IPermission
    {
        foreach ($this->bySlug as $permission) {
            if ($permission->id === $id) {
                return $permission;
            }
        }

        return null;
    }

    /**
     * @return Seq<IPermission>
     */
    public function all(): Seq
    {
        /** @var Seq<IPermission> $items */
        $items = new Seq(array_values($this->bySlug));

        return $items;
    }

    public function has(string $slug): bool
    {
        return isset($this->bySlug[$slug]);
    }
}
