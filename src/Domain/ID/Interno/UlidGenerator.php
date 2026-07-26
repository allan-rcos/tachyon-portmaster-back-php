<?php

declare(strict_types=1);

namespace Domain\ID\Interno;

use Domain\ID\ISequentialIdGenerator;
use Symfony\Component\Uid\Ulid;

/**
 * {@see ISequentialIdGenerator} backed by ULID (symfony/uid).
 *
 * A 26-character Crockford base32 string whose leading 48 bits are the
 * millisecond timestamp, so lexicographic order is chronological order — which
 * is the property the interface promises.
 *
 * This class is the only place that names the ULID library: consumers depend on
 * {@see ISequentialIdGenerator}, so swapping the implementation (a smaller
 * `xid`, say) touches this file alone.
 */
final readonly class UlidGenerator implements ISequentialIdGenerator
{
    public function generate(): string
    {
        return Ulid::generate();
    }
}
