<?php

/**
 * Response Abstract Factory Contract.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation;

use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Builds one outbound message in whichever wire format was negotiated.
 *
 * The mirror of {@see IRequestAbstractFactory}, and asymmetric with it on
 * purpose: an inbound factory is stateless and *receives* the wire data, while
 * an outbound one is constructed **around the message** — including around the
 * factories of any message nested in it, which it builds once in its
 * constructor and never again. An {@see IAcceptsStrategy} calls whichever of
 * the two methods below fits the `Accept` header.
 *
 * The schema's key names live here, not on the message: the DTO is data, and
 * the mapping from its PHP properties to the snake_case field names of the
 * `.fbs` is part of building the representation.
 *
 * Both methods answer with a {@see Result}. A message that cannot be rendered
 * is a server-side failure — the controller turns it into a 502 — and saying so
 * with the same type the rest of the system uses beats throwing across the
 * strategy.
 *
 * The two are also the *only* surface a factory has. A parent nests a child by
 * calling these same methods on it, so nothing outside the contract is ever
 * needed and nobody binds to a concrete factory.
 *
 * @see IAcceptsStrategy What chooses between the two methods.
 * @see IRequestAbstractFactory The inbound half.
 * @see \API\Negociation\DTO\Auth\LoginXResponseFactory A minimal implementation to read first.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IResponseAbstractFactory
{
    /**
     * Writes the message into the builder and answers its table offset.
     *
     * The builder belongs to the caller, and that is what makes nesting work:
     * FlatBuffers stores a child table as an offset into the *same* buffer, so
     * every message in a tree has to be written by the one builder that will be
     * finished at the end. A parent therefore calls this on each of its
     * children — through this interface, never through their classes — and only
     * then starts its own table.
     *
     * Nobody finishes the builder here, not even at the root: the strategy
     * created it and the strategy closes it. A factory maps a message onto a
     * table and stops there.
     *
     * @param  FlatbufferBuilder  $builder  The caller's builder.
     * @return Result<int> This table's offset within it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function createFlatbuffer(FlatbufferBuilder $builder): Result;

    /**
     * The message as an associative structure, keyed by its **schema** field
     * names in snake_case — not by the DTO's PHP property names.
     *
     * @return Result<array<string, mixed>> Ready for `json_encode()`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function createJson(): Result;
}
