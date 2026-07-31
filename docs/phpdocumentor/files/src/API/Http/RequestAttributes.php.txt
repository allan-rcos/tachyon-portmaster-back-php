<?php

/**
 * Request Attributes Enum.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

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
 *
 * The context is per-coroutine, which under OpenSwoole means per-request: two
 * requests being served concurrently in one worker never see each other's
 * values, and everything written here is discarded when the coroutine ends.
 *
 * @see \API\Http\Middleware\RequestIdMiddleware Writes the request id.
 * @see \API\Http\Middleware\FlatBufferNegotiationMiddleware Writes both content kinds.
 * @see \API\Http\Middleware\AuthenticationMiddleware Writes the caller.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum RequestAttributes: string
{
    /**
     * Correlation id for this request, as a string.
     */
    case RequestId = 'request_id';

    /**
     * {@see ContentKind} the request body is encoded in.
     */
    case RequestContentKind = 'request_content_kind';

    /**
     * {@see ContentKind} the response should be encoded in. Independent of the
     * request's: a caller may POST JSON and ask for binary back.
     */
    case ResponseContentKind = 'response_content_kind';

    /**
     * The caller's {@see \App\Context\UserContext}, once authentication has run.
     */
    case AuthenticatedUser = 'authenticated_user';

    /**
     * Reads this attribute from the current coroutine context.
     *
     * @return mixed The stored value, or null when unset or called outside a
     *               coroutine.
     *
     * @copyright 2026 Tachyon
     *
     * @api
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
     * Writes this attribute into the current coroutine context.
     *
     * A no-op outside a coroutine, so a later {@see read()} returns null rather
     * than failing — the readers all treat an absent value as "not set yet".
     *
     * @param  mixed  $value  What to store under this key.
     *
     * @copyright 2026 Tachyon
     *
     * @api
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
