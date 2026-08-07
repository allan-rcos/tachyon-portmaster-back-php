<?php

/**
 * Product Update Request Factory.
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

use API\Fbs\Product\ProductUpdateRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Domain\Enums\RiskClass;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see ProductUpdateXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<ProductUpdateXRequest>
 *
 * @see ProductCreateXRequestFactory The same message on the create side, where the
 *                                   risk-class encodings are explained.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProductUpdateXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<ProductUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new ProductUpdateXRequest(
            name: JsonHelper::nullableString($data, 'name'),
            density: JsonHelper::float($data, 'density'),
            riskClass: RiskClass::tryFrom(JsonHelper::string($data, 'risk_class')) ?? RiskClass::Class1Explosives,
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<ProductUpdateXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = ProductUpdateRequest::getRootAsProductUpdateRequest($buffer);

        return Result::success(new ProductUpdateXRequest(
            name: $table->getName(),
            density: $table->getDensity(),
            riskClass: RiskClass::fromInt((int) $table->getRiskClass()),
        ));
    }
}
