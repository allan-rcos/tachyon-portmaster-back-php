<?php

declare(strict_types=1);

namespace API\Http;

/**
 * HTTP media types used by the API, as a string-backed enum so call sites never
 * carry raw literals.
 */
enum MediaType: string
{
    case Json = 'application/json';
    case ProblemJson = 'application/problem+json';
    case Fbs = 'application/x-flatbuffers';
    case OctetStream = 'application/octet-stream';
}
