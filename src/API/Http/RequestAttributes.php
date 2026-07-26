<?php

declare(strict_types=1);

namespace API\Http;

use OpenSwoole\Coroutine;

/**
 * Keys for per-request state carried in the coroutine context, with typed
 * read/write helpers.
 *
 * Centralising both the key strings and the context access here keeps every
 * other class free of hard-coded context keys (and of a direct dependency on
 * the coroutine API): middlewares write, proxies and logging read.
 */
enum RequestAttributes: string
{
    case RequestId = 'request_id';
    case RequestContentKind = 'request_content_kind';
    case ResponseContentKind = 'response_content_kind';
    case AuthenticatedUser = 'authenticated_user';

    /**
     * Reads this attribute from the current coroutine context, or null when
     * unset / outside a coroutine.
     */
    public function read(): mixed
    {
        $cid = Coroutine::getCid();
        if ($cid <= 0) {
            return null;
        }

        $context = Coroutine::getContext($cid);

        return $context[$this->value] ?? null;
    }

    /**
     * Writes this attribute into the current coroutine context (no-op outside a
     * coroutine).
     */
    public function write(mixed $value): void
    {
        $cid = Coroutine::getCid();
        if ($cid <= 0) {
            return;
        }

        $context = Coroutine::getContext($cid);
        if ($context !== null) {
            $context[$this->value] = $value;
        }
    }
}
