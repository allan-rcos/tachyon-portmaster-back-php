<?php

declare(strict_types=1);

namespace Domain\ID;

/**
 * Generates **sortable** ids for things that are ordered but not stored in a
 * relational index — logs, traces, request correlation.
 *
 * Like a ULID (and the `object_id` family): a timestamp prefix makes the value
 * naturally sequential, so records sort chronologically by id alone, without the
 * cost of a database-grade generator.
 *
 * Inject this when the id needs ordering; use {@see IRandomIdGenerator} when the
 * lookup is exact and order is irrelevant, and {@see IDatabaseIdGenerator} when
 * the id is a primary key.
 */
interface ISequentialIdGenerator extends IIdGenerator
{
}
