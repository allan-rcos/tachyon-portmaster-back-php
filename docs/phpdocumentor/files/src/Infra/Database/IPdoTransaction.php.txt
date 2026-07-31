<?php

/**
 * PDO Transaction Contract.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Database;

use PDO;
use Shared\Exceptions\Result;

/**
 * Hands a repository the connection enlisted in the caller's open boundary.
 *
 * The counterpart of {@see IUnitOfWork}, and separate from it on purpose: a
 * repository needs the connection but has no business opening or closing the
 * boundary, and a use case owns the boundary but has no business touching the
 * connection. Each side receives exactly one of the two contracts, so neither
 * can reach across — a repository that could `commit()` would silently truncate
 * its caller's work.
 *
 * There is no `begin()` here, so the transaction must already be open:
 * {@see getTransaction()} fails when it is not, rather than quietly starting one
 * whose lifetime nobody owns.
 *
 * @see IUnitOfWork The other half — who opens and closes the boundary.
 * @see Interno\PdoTransactionSession The implementation.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IPdoTransaction
{
    /**
     * The connection the current boundary is running on.
     *
     * Asked for on every repository call rather than held, because which
     * connection it is depends on which coroutine is asking.
     *
     * @return Result<PDO> Failure 500 when no boundary is open in the current
     *                     coroutine — a caller forgot {@see IUnitOfWork::begin()}.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function getTransaction(): Result;
}
