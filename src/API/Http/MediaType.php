<?php

/**
 * Media Type Enum.
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
 * HTTP media types used by the API, as a string-backed enum so call sites never
 * carry raw literals.
 *
 * @see ContentKind The binary/JSON choice these are the concrete names of.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
enum MediaType: string
{
    /**
     * A JSON payload.
     */
    case Json = 'application/json';

    /**
     * An RFC 7807 problem document — what every failure is returned as.
     */
    case ProblemJson = 'application/problem+json';

    /**
     * A FlatBuffer payload. What this API advertises for binary.
     */
    case Fbs = 'application/x-flatbuffers';

    /**
     * Unlabelled binary. Accepted on the way in as a request for FlatBuffers,
     * since not every client can name the type above.
     */
    case OctetStream = 'application/octet-stream';
}
