<?php

/**
 * Delete Container Command.
 *
 * @category Application
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace App\Commands\Container;

use App\Context\UserContext;

/**
 * Removes one container by id.
 *
 * Follows the command shape documented on
 * {@see \App\Commands\Product\CreateProductCommand}, reduced to the caller and
 * one identifier.
 *
 * @see \App\Services\IDeleteContainerUseCase What consumes it.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class DeleteContainerCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the container to remove.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
