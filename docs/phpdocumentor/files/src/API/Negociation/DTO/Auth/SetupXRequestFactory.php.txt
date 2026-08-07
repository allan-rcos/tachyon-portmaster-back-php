<?php

/**
 * Setup Request Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Auth;

use API\Fbs\Auth\SetupRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see SetupXRequest} from either wire format.
 *
 * @implements IRequestAbstractFactory<SetupXRequest>
 *
 * @see LoginXRequestFactory The inbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class SetupXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<SetupXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new SetupXRequest(
            name: JsonHelper::nullableString($data, 'name'),
            email: JsonHelper::nullableString($data, 'email'),
            password: JsonHelper::nullableString($data, 'password'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<SetupXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = SetupRequest::getRootAsSetupRequest($buffer);

        return Result::success(new SetupXRequest(
            name: $table->getName(),
            email: $table->getEmail(),
            password: $table->getPassword(),
        ));
    }
}
