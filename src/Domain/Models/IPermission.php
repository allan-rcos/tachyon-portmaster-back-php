<?php

/**
 * Permission Contract.
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
 * An authorization permission — the first citizen of the project's **system
 * metadata** family.
 *
 * Metadata is anything that grows as the code grows, needs to be queried at
 * runtime, and can be rebuilt identically on every restart. A permission
 * qualifies on all three, which is why it is not an enum: an enum would force
 * the domain to enumerate every permission the application layer will ever
 * invent, inverting the dependency. The domain declares only the shape; the
 * application layer owns the values (each use case registers its own at boot)
 * and infrastructure owns the storage.
 *
 * It carries nothing but its identity — see
 * {@see docs/adr/0011-cache-em-processo-openswoole.md} for why there is no label
 * or description.
 *
 * {@see IMarkerGroup} is the same family. A closed,
 * business-defined set like {@see \Domain\Enums\ContainerStatus} stays an enum.
 *
 * @see \Infra\Repository\IPermissionRepository Where these are stored.
 * @see \App\Security\AuthorizesWithPermission How a use case declares its own.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Where the registry lives, and why.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IPermission
{
    /**
     * The stable identifier, `domain:action` (e.g. `product:create`). This is the
     * value that travels on the wire and is persisted against a role.
     *
     * @var string Lower-kebab resource, a colon, lower-kebab action.
     */
    public string $slug {
        get;
    }

    /**
     * The registry's internal index, assigned on registration.
     *
     * A handle for the lookup table: never persisted against a role. It is
     * stable only while the process lives — the catalogue is rebuilt from code at
     * every `WorkerStart`, and an id is the position a slug happened to be
     * declared in — so nothing may depend on a particular value surviving a
     * restart, or a slug being added above it.
     *
     * @var int Registry index; zero means built but not yet registered.
     */
    public int $id {
        get;
    }

    /**
     * The same permission carrying the registry index.
     *
     * Used by the repository at registration time, since the id is not knowable
     * when the domain builds the permission.
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
