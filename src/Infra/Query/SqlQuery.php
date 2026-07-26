<?php

declare(strict_types=1);

namespace Infra\Query;

/**
 * A compiled SQL statement: the text plus its bindings.
 *
 * Produced by a {@see ISqlDQL} — always through the Atlas builder, never by
 * concatenating strings — and executed agnostically by
 * {@see \Infra\Query\Interno\SqlQueryRepository}, which never builds SQL itself.
 *
 * Each binding is Atlas's `[value, PDO type]` pair rather than a bare value, so
 * the runner can `bindValue()` with the right type. That matters: bound as a
 * string, a `LIMIT` placeholder is rejected by MySQL.
 */
final readonly class SqlQuery
{
    /**
     * @param  array<string, array{0: mixed, 1: int|null}>  $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings = [],
    ) {
    }
}
