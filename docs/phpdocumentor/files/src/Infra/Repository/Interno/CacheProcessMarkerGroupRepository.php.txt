<?php

/**
 * Cache Process Marker Group Repository.
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

use Domain\Models\IMarkerGroup;
use Domain\Models\Internal\MarkerGroup;
use Ds\Seq;
use Infra\Repository\IMarkerGroupRepository;
use Shared\Exceptions\Result;

/**
 * {@see IMarkerGroupRepository} over the shared cache.
 *
 * The catalogue of marker groups, registered at `WorkerStart` by whichever
 * feature files markers under one — today, refresh-token revocation. All the
 * machinery is in {@see CacheProcessMetadataRegistry}; this only says what a
 * marker group is.
 *
 * @extends CacheProcessMetadataRegistry<IMarkerGroup>
 *
 * @see IMarkerGroupRepository The contract this implements.
 * @see CacheProcessMetadataRegistry Where the behaviour lives.
 * @see CacheProcessMarkerRepository What files entries under these groups.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class CacheProcessMarkerGroupRepository extends CacheProcessMetadataRegistry implements IMarkerGroupRepository
{
    /**
     * {@inheritDoc}
     *
     * @param  IMarkerGroup  $group  The slug to register; its id is ignored,
     *                               since the catalogue assigns it.
     * @return Result<IMarkerGroup> The registered group, with its id.
     *
     * @copyright 2026 Tachyon
     */
    public function add(IMarkerGroup $group): Result
    {
        return $this->register($group->slug);
    }

    /**
     * {@inheritDoc}
     *
     * @param  string  $slug  What to look for.
     * @return IMarkerGroup|null The group, or `null` when unregistered.
     *
     * @copyright 2026 Tachyon
     */
    public function getBySlug(string $slug): ?IMarkerGroup
    {
        return $this->find($slug);
    }

    /**
     * {@inheritDoc}
     *
     * @param  int  $id  The index to look for.
     * @return IMarkerGroup|null The group, or `null` when nothing carries that
     *                           id.
     *
     * @copyright 2026 Tachyon
     */
    public function getById(int $id): ?IMarkerGroup
    {
        return $this->findById($id);
    }

    /**
     * {@inheritDoc}
     *
     * @return Seq<IMarkerGroup> Every registered group, in declaration order.
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
     * @param  string  $slug  The entry's identity.
     * @param  int  $id  Its index in declaration order.
     * @return IMarkerGroup The model.
     *
     * @copyright 2026 Tachyon
     */
    protected function hydrate(string $slug, int $id): IMarkerGroup
    {
        return new MarkerGroup($slug, $id);
    }

    /**
     * {@inheritDoc}
     *
     * @return string `marker group`.
     *
     * @copyright 2026 Tachyon
     */
    protected function label(): string
    {
        return 'marker group';
    }
}
