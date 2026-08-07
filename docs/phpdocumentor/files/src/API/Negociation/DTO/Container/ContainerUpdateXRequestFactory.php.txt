<?php

/**
 * Container Update Request Factory.
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

use API\Fbs\Container\ContainerUpdateRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see ContainerUpdateXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<ContainerUpdateXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerUpdateXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<ContainerUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new ContainerUpdateXRequest(maxCapacity: JsonHelper::float($data, 'max_capacity')));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<ContainerUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = ContainerUpdateRequest::getRootAsContainerUpdateRequest($buffer);

        return Result::success(new ContainerUpdateXRequest(maxCapacity: $table->getMaxCapacity()));
    }
}
