<?php

/**
 * Container Response Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Container;

use API\Fbs\Container\ContainerResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ContainerXResponse} in either wire format.
 *
 * The status is an integer in the schema and its slug in JSON, so the two
 * branches read the enum differently — the one place a field's encodings
 * diverge.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @param  ContainerXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ContainerXResponse $message)
    {
    }


    /**
     * {@inheritDoc}
     *
     * @param  FlatbufferBuilder  $builder  The caller's builder.
     * @return Result<int> This table's offset within it.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function createFlatbuffer(FlatbufferBuilder $builder): Result
    {
        $id   = $this->message->id !== null ? $builder->createString($this->message->id) : 0;
        $code = $this->message->code !== null ? $builder->createString($this->message->code) : 0;

        return Result::success(ContainerResponse::createContainerResponse(
            $builder,
            $id,
            $code,
            $this->message->currentWeight,
            $this->message->maxCapacity,
            $this->message->status->toInt(),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @return Result<array<string, mixed>> Ready for `json_encode()`.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function createJson(): Result
    {
        return Result::success([
            'id'             => $this->message->id,
            'code'           => $this->message->code,
            'current_weight' => $this->message->currentWeight,
            'max_capacity'   => $this->message->maxCapacity,
            'status'         => $this->message->status->value,
        ]);
    }
}
