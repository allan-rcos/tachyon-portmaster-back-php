<?php

declare(strict_types=1);

namespace Infra\Database\Interno;

use Ds\Seq;
use Infra\Database\IUnitOfWork;
use Shared\Exceptions\Result;

/**
 * One boundary over several units of work.
 *
 * A unit of work is not a database transaction — the database is merely the
 * first participant. As soon as a second one exists (an outbox, a cache, a
 * remote resource), a use case must still see a single {@see IUnitOfWork}, not a
 * list it has to drive by hand. This composite is that single view: it is itself
 * an {@see IUnitOfWork}, so nothing above it knows how many participants there
 * are, or that there is more than one.
 *
 * **Ordering.** {@see begin()} and {@see commit()} run in registration order;
 * {@see rollback()} runs in reverse, so a participant is undone before whatever
 * it was layered on top of.
 *
 * **Failure.** This is a best-effort composite, not a two-phase commit — that
 * distinction is worth stating because the guarantee is weaker than the name
 * suggests. `begin()` stops at the first failure and rolls back whatever it had
 * already opened, so no participant is left holding a boundary nobody will
 * close. `commit()` and `rollback()` instead run *every* participant and report
 * the first failure: once a commit is under way, skipping the remaining
 * participants would strand them rather than repair anything.
 */
final readonly class UnitOfWorkIterator implements IUnitOfWork
{
    /** @var Seq<IUnitOfWork> */
    private Seq $participants;

    public function __construct(IUnitOfWork ...$participants)
    {
        /** @var Seq<IUnitOfWork> $seq */
        $seq = new Seq($participants);

        $this->participants = $seq;
    }

    public function begin(): Result
    {
        /** @var Seq<IUnitOfWork> $opened */
        $opened = new Seq();

        foreach ($this->participants as $participant) {
            $result = $participant->begin();

            if (!$result->isSuccess()) {
                // Undo the ones already open, newest first, so the caller is not
                // left with half a boundary that nothing will ever close.
                /** @var Seq<IUnitOfWork> $undo */
                $undo = $opened->reversed();
                foreach ($undo as $started) {
                    $started->rollback();
                }

                return Result::failure($result->getErrorId());
            }

            $opened->push($participant);
        }

        return Result::void();
    }

    public function commit(): Result
    {
        return $this->runAll($this->participants);
    }

    public function rollback(): Result
    {
        /** @var Seq<IUnitOfWork> $reversed */
        $reversed = $this->participants->reversed();

        return $this->runAll($reversed, rollback: true);
    }

    /**
     * Runs every participant and returns the first failure, if any.
     *
     * @param  Seq<IUnitOfWork>  $participants
     * @return Result<null>
     */
    private function runAll(Seq $participants, bool $rollback = false): Result
    {
        $failure = null;

        foreach ($participants as $participant) {
            $result = $rollback ? $participant->rollback() : $participant->commit();

            if (!$result->isSuccess() && $failure === null) {
                $failure = $result->getErrorId();
            }
        }

        return $failure === null ? Result::void() : Result::failure($failure);
    }
}
