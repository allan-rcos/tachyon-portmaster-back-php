<?php

/**
 * Permission Registry.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 * Everything but the three hooks at the bottom is inherited: this class only
 * names its table, its family and its concrete type, and re-types the base's
 * reads to {@see IPermission} so callers are not handed a bare `object`.
 *
 * @see IPermissionRepository The contract this implements.
 * @see SqlMetadataRegistry Where the behaviour actually lives.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @extends SqlMetadataRegistry<IPermission>
 *
 * @internal
 */
final class PermissionRegistry extends SqlMetadataRegistry implements IPermissionRepository
{
    /**
     * Registers the permission's slug, which is the whole of it.
     *
     * @param  IPermission  $permission  Built by the table module, with `id = 0`;
     *                                   only its slug is read.
     * @return Result<IPermission> The registered permission carrying its index;
     *                             a 500 failure when the write fails.
     *
     * @copyright 2026 Tachyon
     */
    public function add(IPermission $permission): Result
    {
        return $this->register($permission->slug);
    }

    /**
     * The registered permission for a slug.
     *
     * @param  string  $slug  `domain:action`.
     * @return ?IPermission Null when unknown, or the read failed.
     *
     * @copyright 2026 Tachyon
     */
    public function getBySlug(string $slug): ?IPermission
    {
        return $this->find($slug);
    }

    /**
     * The registered permission for a registry index.
     *
     * @param  int  $id  The index assigned at registration.
     * @return ?IPermission Null when unknown, or the read failed.
     *
     * @copyright 2026 Tachyon
     */
    public function getById(int $id): ?IPermission
    {
        return $this->findById($id);
    }

    /**
     * Every registered permission, in registration order.
     *
     * @return Seq<IPermission> The whole catalogue; empty before any worker has
     *                          registered anything.
     *
     * @copyright 2026 Tachyon
     */
    public function all(): Seq
    {
        return $this->listAll();
    }

    /**
     * Rebuilds a {@see Permission} from a stored row.
     *
     * @param  string  $slug  The stored slug.
     * @param  int  $id  The stored registry index.
     * @return IPermission The concrete permission.
     *
     * @copyright 2026 Tachyon
     */
    protected function hydrate(string $slug, int $id): IPermission
    {
        return new Permission($slug, $id);
    }

    /**
     * Names this family in error messages and in the log channel.
     *
     * @return string `permission`.
     *
     * @copyright 2026 Tachyon
     */
    protected function label(): string
    {
        return 'permission';
    }

    /**
     * The table backing this family.
     *
     * @return string `permissions`.
     *
     * @copyright 2026 Tachyon
     */
    protected function table(): string
    {
        return 'permissions';
    }
}
