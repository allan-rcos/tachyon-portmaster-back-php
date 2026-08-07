<?php

/**
 * Problem Details Factory.
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

use API\Fbs\Common\ProblemDetails;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ProblemDetailsX} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProblemDetailsXFactory implements IResponseAbstractFactory
{
    /**
     * @param  ProblemDetailsX  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ProblemDetailsX $message)
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
        $type     = $this->message->type !== null ? $builder->createString($this->message->type) : 0;
        $title    = $this->message->title !== null ? $builder->createString($this->message->title) : 0;
        $detail   = $this->message->detail !== null ? $builder->createString($this->message->detail) : 0;
        $instance = $this->message->instance !== null ? $builder->createString($this->message->instance) : 0;

        return Result::success(ProblemDetails::createProblemDetails(
            $builder,
            $type,
            $title,
            $this->message->status,
            $detail,
            $instance,
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
            'type'     => $this->message->type,
            'title'    => $this->message->title,
            'status'   => $this->message->status,
            'detail'   => $this->message->detail,
            'instance' => $this->message->instance,
        ]);
    }
}
