<?php

/**
 * Pooled PDO Client.
 *
 * @category Infrastructure
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Infra\Database\Pool\Interno;

use OpenSwoole\Core\Coroutine\Client\PDOClient;
use PDO;

/**
 * A {@see PDOClient} that exposes the raw underlying {@see PDO} handle.
 *
 * `PDOClient` proxies the real PDO through `__call`, which means libraries that
 * type-hint a concrete `PDO` (such as Atlas\Query) cannot consume it, and IDEs
 * flag "method not found" on every query call. The pool keeps the full client —
 * its proxy-only features (reconnect, heartbeat) are used purely for pool
 * housekeeping — while handing the raw `PDO` to the transaction/repositories so
 * they can use Atlas directly.
 *
 * @see OpenSwoolePDOClientPool What pools these.
 * @see PooledPDOClientFactory What constructs them.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final class PooledPDOClient extends PDOClient
{
    /**
     * The real PDO connection wrapped by this client.
     *
     * `PDOClient` constructs the underlying connection in {@see PDO::ERRMODE_SILENT}
     * (its retry/heartbeat logic inspects error codes itself). Consumers here use
     * Atlas\Query and rely on exceptions, so the raw handle is switched to
     * {@see PDO::ERRMODE_EXCEPTION} at this single choke point — keeping the pool
     * and the transaction/repositories oblivious to the client's silent mode.
     *
     * The attribute is set on every call rather than once at construction, so a
     * handle the client reconnected underneath still comes back throwing.
     *
     * @return PDO The same handle each time, not a copy — returning it to the
     *             pool is what ends the borrower's claim on it.
     *
     * @copyright 2026 Tachyon
     */
    public function getPdo(): PDO
    {
        /** @var PDO $pdo */
        $pdo = $this->__object;
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
