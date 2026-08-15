<?php

/**
 * Cache Process Permission Repository.
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
 * {@see IPermissionRepository} over the shared cache.
 *
 * The catalogue of permission slugs, filled at `WorkerStart` from the use case
 * constructors that declare them and read on the authorization path of every
 * request afterwards. All the machinery is in
 * {@see CacheProcessMetadataRegistry}; this only says what a permission is.
 *
 * @extends CacheProcessMetadataRegistry<IPermission>
 *
 * @see IPermissionRepository The contract this implements.
 * @see CacheProcessMetadataRegistry Where the behaviour lives.
 * @see \App\Security\AuthorizesWithPermission What fills it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class CacheProcessPermissionRepository extends CacheProcessMetadataRegistry implements IPermissionRepository
{
    /**
     * {@inheritDoc}
     *
     * @param  IPermission  $permission  The slug to register; its id is ignored,
     *                                   since the catalogue assigns it.
     * @return Result<IPermission> The registered permission, with its id.
     *
     * @copyright 2026 Tachyon
     */
    public function add(IPermission $permission): Result
    {
        return $this->register($permission->slug);
    }

    /**
     * {@inheritDoc}
     *
     * @param  string  $slug  What to look for.
     * @return IPermission|null The permission, or `null` when unregistered.
     *
     * @copyright 2026 Tachyon
     */
    public function getBySlug(string $slug): ?IPermission
    {
        return $this->find($slug);
    }

    /**
     * {@inheritDoc}
     *
     * @param  int  $id  The index to look for.
     * @return IPermission|null The permission, or `null` when nothing carries
     *                          that id.
     *
     * @copyright 2026 Tachyon
     */
    public function getById(int $id): ?IPermission
    {
        return $this->findById($id);
    }

    /**
     * {@inheritDoc}
     *
     * @return Seq<IPermission> Every registered permission, in declaration
     *                          order.
     *
     * @copyright 2026 Tachyon
     */
    public function all(): Seq
    {
        return $this->listAll();
    }

    /**
     * {@inheritDoc}
     *
     * @param  list<string>  $slugs  Candidates, in `domain:action` form.
     * @return list<string> Those absent from the catalogue.
     *
     * @copyright 2026 Tachyon
     */
    public function unknown(array $slugs): array
    {
        return $this->missing($slugs);
    }

    /**
     * {@inheritDoc}
     *
     * @param  string  $slug  The entry's identity.
     * @param  int  $id  Its index in declaration order.
     * @return IPermission The model.
     *
     * @copyright 2026 Tachyon
     */
    protected function hydrate(string $slug, int $id): IPermission
    {
        return new Permission($slug, $id);
    }

    /**
     * {@inheritDoc}
     *
     * @return string `permission`.
     *
     * @copyright 2026 Tachyon
     */
    protected function label(): string
    {
        return 'permission';
    }
}
