<?php

/**
 * Permission Repository Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Repository;

use Domain\Models\IPermission;
use Ds\Seq;
use Shared\Exceptions\Result;

/**
 * The permission registry: the runtime catalogue of every permission the
 * application layer declared.
 *
 * Not a persistence repository, despite the name. Permissions are system
 * metadata — fully derivable from the code — so a durable copy would only be a
 * second source of truth able to drift from it. The catalogue is filled at
 * `WorkerStart` by the use cases themselves and rebuilt identically on every
 * restart. What *is* persisted is the slug, on the role that grants it.
 *
 * `POST /setup` reads {@see all()} to grant the first role everything
 * registered, which is why a permission introduced by a new use case needs no
 * list updated anywhere.
 *
 * The reads return bare values rather than {@see Result}: they are consulted on
 * the authorization path of every request, where "not registered" is an answer
 * and not an error.
 *
 * @see IPermission What is registered.
 * @see \Infra\Repository\Interno\CacheProcessPermissionRepository The implementation.
 * @see docs/adr/0011-cache-em-processo-openswoole.md Where the registry lives, and why.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IPermissionRepository
{
    /**
     * Registers a permission and returns it carrying its assigned registry index.
     *
     * Idempotent by slug: registering the same slug twice returns the already
     * registered permission instead of duplicating it. That is what makes four
     * workers declaring the same permission at boot collapse to one entry, and
     * what makes restarting the API against a live database a no-op.
     *
     * @param  IPermission  $permission  Built by the table module, with `id = 0`.
     * @return Result<IPermission> The registered permission, carrying its index;
     *                             a 500 failure when the write fails.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function add(IPermission $permission): Result;

    /**
     * The registered permission for a slug.
     *
     * @param  string  $slug  `domain:action`.
     * @return ?IPermission Null when the slug was never registered.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function getBySlug(string $slug): ?IPermission;

    /**
     * The registered permission for a registry index.
     *
     * @param  int  $id  The index assigned at registration.
     * @return ?IPermission Null when the index is unknown.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function getById(int $id): ?IPermission;

    /**
     * Every registered permission, in registration order.
     *
     * @return Seq<IPermission> The whole catalogue; empty before any worker has
     *                          registered anything.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function all(): Seq;

    /**
     * Which of these slugs the catalogue does not hold.
     *
     * The reason this repository is reachable from the application layer at all.
     * A role is persisted as a list of slugs, so nothing in the schema stops one
     * naming a permission no use case ever declared — a role that silently grants
     * `invented:thing` forever. This is what a role use case asks before it
     * accepts the list.
     *
     * A batch rather than a `has()` per slug: the catalogue is one entry, so
     * answering twenty slugs costs exactly what answering one does. It also lets
     * the caller name **every** offending slug in a single 422 instead of making
     * a client discover them one round trip at a time.
     *
     * It reads, and by design does not judge. Whether an unknown slug is a 422,
     * a warning or a silent drop is a decision about state and therefore the use
     * case's — see {@see \App\Services\Interno\CreateRoleUseCase}.
     *
     * @param  list<string>  $slugs  Candidates, in `domain:action` form.
     * @return list<string> Those absent from the catalogue, in the order given
     *                      and without duplicates. Empty means every one exists.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function unknown(array $slugs): array;
}
