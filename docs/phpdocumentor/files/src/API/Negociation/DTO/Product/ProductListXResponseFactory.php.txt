<?php

/**
 * Product List Response Factory.
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

use API\Fbs\Product\ProductListResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ProductListXResponse} in either wire format.
 *
 * The canonical example of a vector: every row's offset is created first, on
 * the same builder, and only then does the vector — and after it this table —
 * get started.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProductListXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var list<IResponseAbstractFactory> One factory per data row, wrapped
     *                                     once, in the order the message holds
     *                                     them.
     */
    private array $dataFactories;

    /**
     * @param  ProductListXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ProductListXResponse $message)
    {
        $this->dataFactories = array_map(
            static fn (ProductXResponse $item): IResponseAbstractFactory => new ProductXResponseFactory($item),
            $message->data,
        );
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
        $itemOffsets = array_map(
            static fn (IResponseAbstractFactory $item): int => $item->createFlatbuffer($builder)->getValue(),
            $this->dataFactories,
        );
        $data = ProductListResponse::createDataVector($builder, $itemOffsets);

        $nextCursor = $this->message->nextCursor !== null
            ? $builder->createString($this->message->nextCursor)
            : 0;

        return Result::success(ProductListResponse::createProductListResponse($builder, $data, $nextCursor, $this->message->total));
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
            'data' => array_map(
                static fn (IResponseAbstractFactory $item): array => $item->createJson()->getValue(),
                $this->dataFactories,
            ),
            'next_cursor' => $this->message->nextCursor,
            'total'       => $this->message->total,
        ]);
    }
}
