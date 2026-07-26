<?php

declare(strict_types=1);

namespace App\Services;

use App\Commands\Marker\SetMarkerCommand;
use Shared\Exceptions\Result;

interface ISetMarkerUseCase
{
    /**
     * Moves a marker's flag, enforcing which moves are legal.
     *
     * The transitions are the whole point of the abstraction — they are what
     * turns a boolean into single-use semantics:
     *
     * | current  | requested | outcome                                        |
     * |----------|-----------|------------------------------------------------|
     * | absent   | `true`    | created; a marker is born live                 |
     * | `true`   | `true`    | **409** — issued twice, so one of them is a forgery |
     * | `true`   | `false`   | consumed. The happy path                       |
     * | `false`  | `true`    | **409** — a consumed value cannot come back    |
     * | `false`  | `false`   | success, no write. Already consumed            |
     * | absent   | `false`   | success, no write. Never live, so already dead |
     *
     * The two failures are conflicts, not bad requests: the caller's input is
     * well-formed, the *state* refuses it. Callers that treat "not live" as an
     * authentication failure (the refresh flow) translate the 409 into their own
     * 401 — this use case has no opinion about who is asking.
     *
     * Consuming is a write, never a delete, and the two idempotent rows write
     * nothing at all. Keeping consumed markers is what lets a replay be told
     * apart from a value that was never issued, right up until the TTL expires.
     *
     * @return Result<null> 409 on an illegal transition, 404 when the group is
     *                      not registered, 422 when the value is empty.
     */
    public function execute(SetMarkerCommand $command): Result;
}
