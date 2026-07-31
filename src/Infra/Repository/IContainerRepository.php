<?php

/**
 * Container Repository Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Infra\Repository;

use Domain\Models\IContainer;
use Shared\Exceptions\Result;

/**
 * Persistence for containers — the write side.
 *
 * Stores what the table module already validated; it does not validate, and it
 * does not enforce status transitions. Every method enlists in the caller's open
 * transaction, so a use case that rolls back undoes these too.
 *
 * Only the container row itself. Its cargo lines and telemetry live in
 * {@see IManifestRepository}, and listing goes through
 * {@see \Infra\Query\IQueryRepository}, which returns views rather than domain
 * models.
 *
 * @see IContainer What is stored.
 * @see \Domain\TableModules\IContainerTM Validates before anything reaches here.
 * @see \Infra\Repository\Interno\SqlContainerRepository The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IContainerRepository
{
    /**
     * Loads a container by id.
     *
     * The manifest does not come with it — cargo is loaded separately through
     * {@see IManifestRepository}.
     *
     * @param  string  $id  Base62 id as it travels the application.
     * @return Result<IContainer> A 404 failure when no row matched; a 500 when
     *                            the read itself failed, which includes an id
     *                            too malformed to decode.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function findById(string $id): Result;

    /**
     * Writes a new container.
     *
     * @param  IContainer  $container  Already validated by the table module.
     * @return Result<null> Void on success; a 500 failure on any write error,
     *                      including a constraint the database rejected.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function insert(IContainer $container): Result;

    /**
     * Overwrites an existing container, matched on its id.
     *
     * Whatever status the container carries is written as given; deciding
     * whether the move was allowed happened in the table module.
     *
     * @param  IContainer  $container  The new state, already validated.
     * @return Result<null> Void on success; a 500 failure on a write error.
     *                      Matching no row is *not* a failure — callers that
     *                      care load the container first.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function update(IContainer $container): Result;

    /**
     * Removes a container.
     *
     * Its cargo lines and telemetry go with it — both tables cascade on delete —
     * so a loaded container can be removed without clearing the manifest first.
     *
     * @param  string  $id  Base62 id.
     * @return Result<null> Void on success; a 500 failure on a write error.
     *                      Matching no row is *not* a failure — the use case
     *                      loads the container first, and that is where the 404
     *                      comes from.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function delete(string $id): Result;
}
