<?php

declare(strict_types=1);

namespace API\Controllers;

use API\Http\Session;
use App\Context\UserContext;
use Shared\Exceptions\Result;

/**
 * Resolves the caller for a controller action as a {@see Result}.
 *
 * Every guarded action opens the same way, so branching on the Result keeps it
 * to a few lines at each call site:
 *
 * ```php
 * $caller = $this->caller();
 * if (!$caller->isSuccess()) {
 *     return ProblemResponse::fromResult($caller);
 * }
 * $context = $caller->getValue();
 * ```
 *
 * This is the *only* authorization-adjacent thing a controller still does —
 * establish identity. Whether that identity may perform the action is decided by
 * the use case.
 */
trait ResolvesCaller
{
    /**
     * @return Result<UserContext>
     */
    private function caller(): Result
    {
        return Session::require();
    }

    /**
     * The `{id}` path variable, or an empty string when absent — the use case
     * will answer 404 for it, which is the right answer anyway.
     */
    private function pathId(\Psr\Http\Message\ServerRequestInterface $request): string
    {
        $id = $request->getAttribute('id');

        return is_string($id) ? $id : '';
    }
}
