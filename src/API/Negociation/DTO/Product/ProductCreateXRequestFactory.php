<?php

/**
 * Product Create Request Factory.
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

use API\Fbs\Product\ProductCreateRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Domain\Enums\RiskClass;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see ProductCreateXRequest} from either wire format.
 *
 * The risk class is the one field whose two encodings differ: an integer in the
 * schema, its slug in JSON. Both fall back to the first class rather than
 * failing, leaving the 422 to the table module.
 *
 * @implements IRequestAbstractFactory<ProductCreateXRequest>
 *
 * @see \API\Negociation\DTO\Auth\LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProductCreateXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<ProductCreateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new ProductCreateXRequest(
            name: JsonHelper::nullableString($data, 'name'),
            density: JsonHelper::float($data, 'density'),
            riskClass: RiskClass::tryFrom(JsonHelper::string($data, 'risk_class')) ?? RiskClass::Class1Explosives,
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<ProductCreateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = ProductCreateRequest::getRootAsProductCreateRequest($buffer);

        return Result::success(new ProductCreateXRequest(
            name: $table->getName(),
            density: $table->getDensity(),
            riskClass: RiskClass::fromInt((int) $table->getRiskClass()),
        ));
    }
}
