<?php

/**
 * Cargo Manifest Item Factory.
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

use API\Fbs\Container\CargoManifestItem;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see CargoManifestItemX} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class CargoManifestItemXFactory implements IResponseAbstractFactory
{
    /**
     * @param  CargoManifestItemX  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private CargoManifestItemX $message)
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
        $productId = $this->message->productId !== null
            ? $builder->createString($this->message->productId)
            : 0;
        $productName = $this->message->productName !== null
            ? $builder->createString($this->message->productName)
            : 0;

        return Result::success(CargoManifestItem::createCargoManifestItem(
            $builder,
            $productId,
            $productName,
            $this->message->quantity,
            $this->message->weight,
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
            'product_id'   => $this->message->productId,
            'product_name' => $this->message->productName,
            'quantity'     => $this->message->quantity,
            'weight'       => $this->message->weight,
        ]);
    }
}
