<?php

declare(strict_types=1);

namespace Infra\Repository\Interno;

use Domain\Models\IMarkerGroup;
use Domain\Models\Internal\MarkerGroup;
use Ds\Seq;
use Infra\Repository\IMarkerGroupRepository;
use Shared\Exceptions\Result;

/**
 * {@see IMarkerGroupRepository} over the `marker_groups` table, filled at
 * WorkerStart.
 *
 * @extends SqlMetadataRegistry<IMarkerGroup>
 */
final class MarkerGroupRegistry extends SqlMetadataRegistry implements IMarkerGroupRepository
{
    public function add(IMarkerGroup $group): Result
    {
        return $this->register($group->slug);
    }

    public function getBySlug(string $slug): ?IMarkerGroup
    {
        return $this->find($slug);
    }

    public function getById(int $id): ?IMarkerGroup
    {
        return $this->findById($id);
    }

    /**
     * @return Seq<IMarkerGroup>
     */
    public function all(): Seq
    {
        return $this->listAll();
    }

    protected function hydrate(string $slug, int $id): IMarkerGroup
    {
        return new MarkerGroup($slug, $id);
    }

    protected function label(): string
    {
        return 'marker group';
    }

    protected function table(): string
    {
        return 'marker_groups';
    }
}
