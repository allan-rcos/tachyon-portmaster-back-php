<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Domain\Models\Internal\Permission;
use Domain\Models\IPermission;
use Ds\Seq;
use Infra\Repository\IPermissionRepository;
use Shared\Exceptions\Result;

/**
 * {@see IPermissionRepository} over the `permissions` table, filled at
 * WorkerStart by the application layer's permission registration.
 *
 * @extends SqlMetadataRegistry<IPermission>
 */
final class PermissionRegistry extends SqlMetadataRegistry implements IPermissionRepository
{
    public function add(IPermission $permission): Result
    {
        return $this->register($permission->slug);
    }

    public function getBySlug(string $slug): ?IPermission
    {
        return $this->find($slug);
    }

    public function getById(int $id): ?IPermission
    {
        return $this->findById($id);
    }

    /**
     * @return Seq<IPermission>
     */
    public function all(): Seq
    {
        return $this->listAll();
    }

    protected function hydrate(string $slug, int $id): IPermission
    {
        return new Permission($slug, $id);
    }

    protected function label(): string
    {
        return 'permission';
    }

    protected function table(): string
    {
        return 'permissions';
    }
}
