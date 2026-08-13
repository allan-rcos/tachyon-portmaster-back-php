<?php

/**
 * Seal Container Command.
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
 * Seals one container, closing it for further loading.
 *
 * A transition command: it names the container and nothing else, because the
 * target status is the command's identity. Whether the move is legal from where
 * the container currently is belongs to the table module, which refuses it with
 * a 409.
 *
 * @see \App\Services\ISealContainerUseCase What consumes it.
 * @see DispatchContainerCommand The next transition.
 * @see \App\Commands\Product\CreateProductCommand The command shape.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class SealContainerCommand
{
    /**
     * @param  UserContext  $context  The caller.
     * @param  string  $id  Base62 id of the container to seal.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public UserContext $context,
        public string $id,
    ) {
    }
}
