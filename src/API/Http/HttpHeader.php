<?php

declare(strict_types=1);

namespace API\Http;

/**
 * HTTP header names referenced by the stack, as a string-backed enum to avoid
 * scattering raw header literals across middlewares and controllers.
 */
enum HttpHeader: string
{
    case Accept = 'Accept';
    case ContentType = 'Content-Type';
    case RequestId = 'X-Request-Id';
    case SetCookie = 'Set-Cookie';
}
