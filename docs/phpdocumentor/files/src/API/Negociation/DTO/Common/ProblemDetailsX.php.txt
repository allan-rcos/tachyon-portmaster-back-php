<?php

/**
 * Problem Details Message.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Common;

/**
 * An RFC 7807 problem document — the body of every failed request.
 *
 * It is a table in the published schema like any other, so it negotiates like
 * any other: a caller asking for binary gets the error in binary. What stays
 * special is only the media type, `application/problem+json` on the JSON
 * branch, which follows from the status code in
 * {@see \API\Http\ContentKind::mediaType()}.
 *
 * @see ProblemDetailsXFactory What renders this onto the wire.
 * @see \API\Http\ProblemResponse What builds it.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProblemDetailsX
{
    /**
     * @param  ?string  $type  URI identifying the problem kind.
     * @param  ?string  $title  Short, stable summary of the problem kind.
     * @param  int  $status  HTTP status, repeated in the document.
     * @param  ?string  $detail  What went wrong on this occasion.
     * @param  ?string  $instance  URI of the occurrence, when there is one.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(
        public ?string $type = null,
        public ?string $title = null,
        public int $status = 0,
        public ?string $detail = null,
        public ?string $instance = null,
    ) {
    }
}
