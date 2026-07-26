<?php

declare(strict_types=1);

namespace Domain\ID;

/**
 * Generates **unpredictable** ids for exact-match lookups — opaque tokens,
 * public handles, anything that must not be guessable or enumerable.
 *
 * Implemented by {@see \Domain\ID\Interno\NanoIdGenerator}. Carrying no
 * timestamp and no sequence, the value leaks nothing about when or in what order
 * it was minted; the flip side is that it sorts arbitrarily, so it must not be
 * used where ordering or index locality matters
 * ({@see ISequentialIdGenerator} / {@see IDatabaseIdGenerator}).
 */
interface IRandomIdGenerator extends IIdGenerator
{
}
