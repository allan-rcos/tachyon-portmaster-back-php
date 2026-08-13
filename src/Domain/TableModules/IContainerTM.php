<?php

/**
 * Container Table Module Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Domain\TableModules;

use Domain\Models\IContainer;
use Shared\Exceptions\Result;

/**
 * The rules a container must satisfy, and every transition it may make.
 *
 * The lifecycle — empty, loading, sealed, in transit — is enforced here and
 * nowhere else. Each transition takes the current container and answers with
 * the next one, or with the reason it cannot happen; a use case never inspects
 * a status and decides for itself.
 *
 * Loading and unloading are not here: they change the container *and* its cargo
 * together, so they belong to {@see IManifestTM}.
 *
 * @see IContainer What gets built.
 * @see \Domain\Enums\ContainerStatus The states.
 * @see \Domain\TableModules\Interno\ContainerTM The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IContainerTM
{
    /**
     * Builds a new container.
     *
     * Current weight 0 and status `Empty` are forced, ignoring any client
     * attempt to set them — a container that claimed to arrive already loaded
     * would have no cargo rows behind the claim.
     *
     * @param  string  $code  Yard-facing identifier; required. Uniqueness is the
     *                        database's to enforce, not this module's.
     * @param  float  $maxCapacity  Kilograms; must be greater than zero.
     * @return Result<IContainer> A 422 failure listing every field that broke a rule.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function create(string $code, float $maxCapacity): Result;

    /**
     * Produces the container with an updated capacity.
     *
     * Only the capacity is updatable: the code is the yard's handle on a
     * physical object, and the weight and status are consequences of what has
     * been loaded.
     *
     * @param  IContainer  $container  Current state.
     * @param  float  $maxCapacity  New capacity in kilograms; must be greater
     *                              than zero.
     * @return Result<IContainer> A 422 failure when the capacity is invalid.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function update(IContainer $container, float $maxCapacity): Result;

    /**
     * Seals the container, moving it to `Sealed`.
     *
     * Requires status `Loading` and at least 10% of capacity filled. The floor
     * is what stops a nearly-empty container being dispatched as though it were
     * a load.
     *
     * @param  IContainer  $container  Current state.
     * @return Result<IContainer> A 409 failure when the container is not
     *                            `Loading`, or is under the minimum fill.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function seal(IContainer $container): Result;

    /**
     * Dispatches the container, moving it to `InTransit`.
     *
     * Requires status `Sealed`, which is also what makes dispatch a one-way
     * move — a container already in transit is no longer sealed, so a second
     * call is refused.
     *
     * @param  IContainer  $container  Current state.
     * @return Result<IContainer> A 409 failure when the container is not `Sealed`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function dispatch(IContainer $container): Result;
}
