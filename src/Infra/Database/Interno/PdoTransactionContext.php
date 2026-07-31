<?php

/**
 * PDO Transaction Context.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

namespace Infra\Database\Interno;

use PDO;

/**
 * What a coroutine stores while it has a boundary open: the connection the
 * boundary is running on.
 *
 * A wrapper rather than the bare {@see PDO}, so that what lives in the
 * coroutine's context is a type of this layer's own — the session stores and
 * retrieves one of these, and what else a boundary might need to carry can be
 * added here without every reader changing.
 *
 * @see PdoTransactionSession What stores and reads it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
readonly class PdoTransactionContext
{
    /**
     * @param  PDO  $pdo  The leased connection, with its transaction already
     *                    open.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public PDO $pdo
    ) {
    }
}
