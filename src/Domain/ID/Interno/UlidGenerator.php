<?php

/**
 * ULID Generator.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\ID\Interno;

use Domain\ID\ISequentialIdGenerator;
use Symfony\Component\Uid\Ulid;

/**
 * Chronologically sortable ids, from ULID.
 *
 * A 26-character Crockford base32 string whose leading 48 bits are the
 * millisecond timestamp, so lexicographic order is chronological order — the
 * property {@see ISequentialIdGenerator} promises.
 *
 * The only place that names the ULID library: callers depend on the interface,
 * so swapping the implementation for a smaller `xid` would touch this file
 * alone.
 *
 * @see ISequentialIdGenerator The contract, and when to choose this flavour.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @internal
 */
final readonly class UlidGenerator implements ISequentialIdGenerator
{
    /**
     * Mints a ULID.
     *
     * @return string 26 Crockford base32 characters, sorting chronologically.
     *
     * @copyright 2026 Tachyon
     */
    public function generate(): string
    {
        return Ulid::generate();
    }
}
