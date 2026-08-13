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
}
