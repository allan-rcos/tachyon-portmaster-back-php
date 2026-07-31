<?php

/**
 * Database Id Generator Contract.
 *
 * @category Domain
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace Domain\ID;

/**
 * Generates ids for entities that are **persisted**.
 *
 * Implemented by {@see \Domain\ID\Interno\SnowflakeIdGenerator}: a 63-bit
 * time-ordered integer, compacted to base62. The monotonic prefix keeps the
 * BIGINT primary key append-only, which is what makes the index cheap — that is
 * the whole reason this flavour exists and why it must not be used for ids that
 * never reach a table.
 *
 * Inject this (never {@see IIdGenerator}) wherever the id ends up in a column.
 *
 * @see IIdGenerator The shared signature, and the other two flavours.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IDatabaseIdGenerator extends IIdGenerator
{
}
