<?php

/**
 * Unload Item Request Factory.
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

use API\Fbs\Manifest\UnloadItemRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds an {@see UnloadItemXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<UnloadItemXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UnloadItemXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<UnloadItemXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new UnloadItemXRequest(
            containerId: JsonHelper::nullableString($data, 'container_id'),
            productId: JsonHelper::nullableString($data, 'product_id'),
            quantity: JsonHelper::float($data, 'quantity'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<UnloadItemXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = UnloadItemRequest::getRootAsUnloadItemRequest($buffer);

        return Result::success(new UnloadItemXRequest(
            containerId: $table->getContainerId(),
            productId: $table->getProductId(),
            quantity: $table->getQuantity(),
        ));
    }
}
