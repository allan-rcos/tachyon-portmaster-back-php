<?php

/**
 * Product Response Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Product;

use API\Fbs\Product\ProductResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ProductXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProductXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @param  ProductXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ProductXResponse $message)
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
        $name = $this->message->name !== null ? $builder->createString($this->message->name) : 0;

        return Result::success(ProductResponse::createProductResponse(
            $builder,
            $id,
            $name,
            $this->message->density,
            $this->message->riskClass->toInt(),
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
            'id'         => $this->message->id,
            'name'       => $this->message->name,
            'density'    => $this->message->density,
            'risk_class' => $this->message->riskClass->value,
        ]);
    }
}
