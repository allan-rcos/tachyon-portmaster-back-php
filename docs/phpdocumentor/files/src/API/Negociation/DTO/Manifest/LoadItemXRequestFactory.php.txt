<?php

/**
 * Load Item Request Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Manifest;

use API\Fbs\Manifest\LoadItemRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see LoadItemXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<LoadItemXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoadItemXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<LoadItemXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new LoadItemXRequest(
            containerId: JsonHelper::nullableString($data, 'container_id'),
            productId: JsonHelper::nullableString($data, 'product_id'),
            quantity: JsonHelper::float($data, 'quantity'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<LoadItemXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = LoadItemRequest::getRootAsLoadItemRequest($buffer);

        return Result::success(new LoadItemXRequest(
            containerId: $table->getContainerId(),
            productId: $table->getProductId(),
            quantity: $table->getQuantity(),
        ));
    }
}
