<?php

/**
 * HTTP Header Enum.
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

/**
 * HTTP header names referenced by the stack, as a string-backed enum to avoid
 * scattering raw header literals across middlewares and controllers.
 *
 * Only the headers this application reads or writes by name are here; the rest
 * pass through untouched.
 *
 * @see MediaType The values `Accept` and `Content-Type` carry.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum HttpHeader: string
{
    /**
     * What the client wants back; drives the response {@see ContentKind}.
     */
    case Accept = 'Accept';

    /**
     * What the payload is. Read off the request to pick a decoder, written on
     * the response to declare the encoding.
     */
    case ContentType = 'Content-Type';

    /**
     * The per-request correlation id, echoed back on every response.
     */
    case RequestId = 'X-Request-Id';

    /**
     * Carries the auth cookies. Appended rather than set, since a login writes
     * two of them.
     */
    case SetCookie = 'Set-Cookie';

    /**
     * How a cache took part in producing the body, per
     * {@link https://www.rfc-editor.org/rfc/rfc9211 RFC 9211}.
     *
     * The standard field rather than an `X-` name of our own: it is a list, so a
     * CDN or reverse proxy in front adds its own entry instead of colliding with
     * ours, and the vocabulary (`hit`, `fwd=…`, `ttl=…`) is one every cache
     * already speaks.
     *
     * Carries {@see \API\Http\Middleware\CacheHeaderMiddleware::HIT} when the
     * view cache answered, and is **absent** otherwise. The RFC asks a cache to
     * describe its own part and leaves silence meaning "this cache had none",
     * which is what a read that was never cacheable in the first place should
     * say.
     */
    case Cache = 'Cache-Status';
}
