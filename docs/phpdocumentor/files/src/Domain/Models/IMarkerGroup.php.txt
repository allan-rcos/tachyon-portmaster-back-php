<?php

/**
 * Marker Group Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\Models;

/**
 * A namespace for {@see IMarker} flags — **system metadata**, same family as
 * {@see IPermission}.
 *
 * The group is what keeps markers from colliding. Two features hashing
 * unrelated values could produce the same digest, and without a group they
 * would read each other's flags; with one, a marker is only ever found by the
 * pair `(group, key)`.
 *
 * Like the other metadata it carries only its identity — see {@see IPermission}
 * for why there is no label or description.
 *
 * @see \Infra\Repository\IMarkerGroupRepository Where these are stored.
 * @see \App\Services\IRegisterMarkerGroupUseCase How a feature declares one.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IMarkerGroup
{
    /**
     * The stable identifier, a lower-kebab token (e.g. `refresh-token`). Named
     * after the *kind of flag*, never after the value being flagged.
     *
     * @var string Lower-kebab token.
     */
    public string $slug {
        get;
    }

    /**
     * Registry index, assigned on registration. Nothing stores it: markers are
     * filed under the group's *slug*, so this is a handle for lookups and
     * listings rather than a key. It is stable only while the process lives, the
     * catalogue being rebuilt from code at every `WorkerStart`.
     *
     * @var int Registry index; zero means built but not yet registered.
     */
    public int $id {
        get;
    }

    /**
     * The same group carrying the registry index.
     *
     * Used by the repository at registration time, since the id is not knowable
     * when the domain builds the group.
     *
     * @param  int  $id  The index the registry assigned.
     * @return self A copy carrying it; the receiver is unchanged.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function withId(int $id): self;
}
