<?php

declare(strict_types=1);

namespace Infra\Database;

use Shared\Exceptions\Result;

/**
 * Marks the boundary of one atomic piece of work.
 *
 * Deliberately says nothing about *what* is being made atomic: a unit of work is
 * not necessarily a database transaction, and the caller must not be able to
 * tell. A use case opens the boundary, does its business and closes it — the
 * resource behind it is somebody else's problem. Reaching the connection is
 * {@see IPdoTransaction}'s job, and a use case is never handed that contract.
 *
 * Splitting the two is what makes {@see Interno\UnitOfWorkIterator} possible: a
 * composite can begin and commit every participant, but there is no single "the
 * connection" for it to hand out.
 */
interface IUnitOfWork
{
    /**
     * Opens the boundary. Re-entrant calls are ignored — the outermost one owns
     * the boundary, so a nested use case cannot commit its caller's work.
     *
     * @return Result<null>
     */
    public function begin(): Result;

    /**
     * Closes the boundary, making the work durable.
     *
     * @return Result<null>
     */
    public function commit(): Result;

    /**
     * Abandons the work opened by {@see begin()}.
     *
     * @return Result<null>
     */
    public function rollback(): Result;
}
