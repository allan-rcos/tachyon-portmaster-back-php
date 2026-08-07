<?php

/**
 * Request Abstract Factory Contract.
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

use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds one inbound message, from whichever wire format it arrived in.
 *
 * This is the *what*, never the *how*: the factory knows one message — its
 * fields, its schema key names, which generated table backs it — and knows how
 * to produce that message from a decoded JSON structure or from a FlatBuffer.
 * Which of the two is used is not its decision; an {@see IContentTypeStrategy}
 * picks the method and hands over the already-read body.
 *
 * The pairing is deliberate double dispatch. The strategy exists once per wire
 * format for the whole application, the factory once per message, and neither
 * ever grows a branch on the other — the 39 copies of "if the request is JSON,
 * decode it, else parse a buffer" that the old proxies carried collapse into
 * the two strategies.
 *
 * Both methods answer with a {@see Result}, like everything else in the system
 * that can fail: reading a client's bytes is exactly that. What the failure
 * *means* for the response is not decided here — the controller decides, from
 * the same `Result` it gets back through the strategy.
 *
 * **Nothing here rejects a merely incomplete message.** A missing string is
 * null, a missing number is zero. Validation is the domain's, and answering 422
 * with every broken field at once is something only the table modules can do —
 * a factory that failed on a blank field would pre-empt that with a worse
 * error.
 *
 * Nor is one ever asked for a message out of nothing. A request that carried no
 * body never reaches a factory: there would be nothing to build from, and the
 * strategy answers that as the failure it is. Hence two methods and not three.
 *
 * @template T of object The message this factory builds.
 *
 * @see IContentTypeStrategy What chooses between the two methods.
 * @see \API\Negociation\Interno\JsonHelper The narrowing helpers {@see fromJson()} is built on.
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory A minimal implementation to read first.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
interface IRequestAbstractFactory
{
    /**
     * Builds the message from a decoded JSON/associative structure.
     *
     * Every absent or wrongly-typed key falls back to a null or a zero rather
     * than failing; see the class note.
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<T> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result;

    /**
     * Builds the message from a FlatBuffer.
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema,
     *                              positioned at its start.
     * @return Result<T> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result;
}
