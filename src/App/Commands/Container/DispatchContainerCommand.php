<?php

/**
 * Dispatch Container Command.
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
 * Dispatches one sealed container into transit.
 *
 * A transition command, like {@see SealContainerCommand}: it names the container
 * and nothing else. That it must already be sealed is a rule of the table
 * module, not a precondition this command can express — dispatching an unsealed
 * container is refused with a 409.
 *
 * @see \App\Services\IDispatchContainerUseCase What consumes it.
 * @see SealContainerCommand The transition before it.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class DispatchContainerCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the container to dispatch.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
