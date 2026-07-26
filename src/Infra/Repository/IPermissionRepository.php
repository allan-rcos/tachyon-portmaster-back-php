<?php

declare(strict_types=1);

namespace Infra\Repository;

use Domain\Models\IPermission;
use Ds\Seq;
use Shared\Exceptions\Result;

/**
 * The permission registry: the runtime catalogue of every permission the
 * application layer declared.
 *
 * Deliberately **not** a database repository. Permissions are system metadata —
 * fully derivable from the code — so persisting them would only create a second
 * source of truth that can drift from it. The registry is filled at worker start
 * and lives in shared memory for the worker's lifetime; a restart rebuilds it
 * identically.
 *
 * What *is* persisted is the slug, on the role that grants it.
 */
interface IPermissionRepository
{
    /**
     * Registers a permission and returns it carrying its assigned registry index.
     *
     * Idempotent by slug: registering the same slug twice returns the already
     * registered permission instead of duplicating it, so a slug shared by two
     * use cases resolves to one entry.
     *
     * @return Result<IPermission> Failure 500 when the registry is full.
     */
    public function add(IPermission $permission): Result;

    /** The registered permission for this slug, or null when unknown. */
    public function getBySlug(string $slug): ?IPermission;

    /** The registered permission for this registry index, or null when unknown. */
    public function getById(int $id): ?IPermission;

    /**
     * Every registered permission, in registration order.
     *
     * @return Seq<IPermission>
     */
    public function all(): Seq;

    /** Whether a permission with this slug has been registered. */
    public function has(string $slug): bool;
}
