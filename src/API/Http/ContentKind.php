<?php

/**
 * Content Kind Enum.
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
 * Generic wire-format of a payload: FlatBuffer binary or JSON.
 *
 * Negotiation is intentionally reduced to this binary choice, and this enum is
 * only the *parser* for it: it turns each header into one of two answers, and
 * the middleware maps those answers onto the strategies that do the work. The
 * request kind comes from `Content-Type` and the response kind from `Accept`
 * — they are independent, since a caller may POST JSON yet ask for binary back.
 *
 * It also names the kind: {@see mediaType()} is what the negotiation middleware
 * stamps on the response. That lives here and not on the strategy, because a
 * strategy that could be asked which media type it is would be a strategy the
 * caller can identify — and the whole point of the pattern is that nobody
 * downstream knows which one they hold.
 *
 * @see \API\Http\Middleware\ContentNegotiationMiddleware What resolves both kinds, maps them onto strategies, and labels the answer.
 * @see MediaType The concrete names this resolves to.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum ContentKind: string
{
    /**
     * FlatBuffer binary.
     */
    case Fbs = 'fbs';

    /**
     * JSON.
     */
    case Json = 'json';

    /**
     * Resolves the kind a client used for its request body from `Content-Type`.
     *
     * Only `json` is looked for; anything else with a body is read as binary,
     * which is why the default here is {@see self::Fbs} and not the response
     * side's {@see self::Json}.
     *
     * @param  string  $header  The `Content-Type` value, or an empty string.
     * @param  self  $default  Kind to assume when the header is absent.
     * @return self The kind to decode the body as.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromContentType(string $header, self $default = self::Fbs): self
    {
        if ($header === '') {
            return $default;
        }

        return str_contains(strtolower($header), 'json') ? self::Json : self::Fbs;
    }

    /**
     * Resolves the kind a client wants for the response from `Accept`. Anything
     * that is not an explicit binary/fbs media type falls back to the default.
     *
     * @param  string  $header  The `Accept` value, or an empty string.
     * @param  self  $default  Kind to assume when the header is absent or asks
     *                         for neither.
     * @return self The kind to encode the response as.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public static function fromAccept(string $header, self $default = self::Json): self
    {
        $accept = strtolower($header);

        if ($accept === '') {
            return $default;
        }

        if (str_contains($accept, 'json')) {
            return self::Json;
        }

        if (str_contains($accept, 'flatbuffers') || str_contains($accept, 'octet-stream')) {
            return self::Fbs;
        }

        return $default;
    }

    /**
     * The media type a response of this kind and status advertises.
     *
     * The status is what decides between `application/json` and RFC 7807's
     * `application/problem+json`: every 4xx and 5xx in this API *is* a problem
     * document, and a client switching on that media type must keep seeing it.
     * Binary has no such variant — an error and a success carry the same type
     * there, and the status code is what tells them apart.
     *
     * @param  int  $status  The response's HTTP status.
     * @return MediaType What goes in the response's `Content-Type`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function mediaType(int $status = 200): MediaType
    {
        if ($this === self::Fbs) {
            return MediaType::Fbs;
        }

        return $status >= 400 ? MediaType::ProblemJson : MediaType::Json;
    }
}
