<?php

declare(strict_types=1);

namespace API\Http;

/**
 * Generic wire-format of a payload: FlatBuffer binary or JSON.
 *
 * Negotiation is intentionally reduced to this binary choice. The middleware
 * derives the request kind from `Content-Type` and the response kind from
 * `Accept` (they are independent — a caller may POST JSON yet ask for binary
 * back, or vice-versa) and stores both in the coroutine context. The Fbs
 * proxies read them back to decode the inbound body and to build the outbound
 * one.
 */
enum ContentKind: string
{
    case Fbs = 'fbs';
    case Json = 'json';

    /**
     * Resolves the kind a client used for its request body from `Content-Type`.
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
     * The canonical media type to advertise for this kind.
     */
    public function mediaType(): MediaType
    {
        return $this === self::Json ? MediaType::Json : MediaType::Fbs;
    }
}
