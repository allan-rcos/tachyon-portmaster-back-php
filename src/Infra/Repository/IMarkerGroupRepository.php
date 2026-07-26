<?php

declare(strict_types=1);

namespace Infra\Repository;

use Domain\Models\IMarkerGroup;
use Ds\Seq;
use Shared\Exceptions\Result;

/**
 * Registry of marker groups — the same metadata contract as
 * {@see IPermissionRepository}, over the `marker_groups` table.
 *
 * Filled at WorkerStart by whichever feature declares a group, and read on every
 * marker operation to turn a group slug into the id stored on the marker row.
 */
interface IMarkerGroupRepository
{
    /**
     * Registers a group, assigning its index. Idempotent by slug.
     *
     * @return Result<IMarkerGroup>
     */
    public function add(IMarkerGroup $group): Result;

    public function getBySlug(string $slug): ?IMarkerGroup;

    public function getById(int $id): ?IMarkerGroup;

    /**
     * @return Seq<IMarkerGroup>
     */
    public function all(): Seq;

    public function has(string $slug): bool;
}
