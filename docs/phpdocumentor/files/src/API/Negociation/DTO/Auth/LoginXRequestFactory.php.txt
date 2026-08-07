<?php

/**
 * Login Request Factory.
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

use API\Fbs\Auth\LoginRequest;
use API\Negociation\IRequestAbstractFactory;
use API\Negociation\Interno\JsonHelper;
use Google\FlatBuffers\ByteBuffer;
use Shared\Exceptions\Result;

/**
 * Builds a {@see LoginXRequest} from either wire format.
 *
 * The shape every inbound factory follows, written out once here: `fromJson()`
 * narrows the decoded keys, `fromFlatbuffer()` reads the generated table's
 * root, and both land on the same DTO. Nothing validates — see
 * {@see IRequestAbstractFactory}.
 *
 * @implements IRequestAbstractFactory<LoginXRequest>
 *
 * @see IRequestAbstractFactory The contract, and why none of this validates.
 * @see LoginRequest The generated table this reads.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoginXRequestFactory implements IRequestAbstractFactory
{
    /**
     * {@inheritDoc}
     *
     * @param  array<string, mixed>  $data  A `json_decode(..., true)` result.
     * @return Result<LoginXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromJson(array $data): Result
    {
        return Result::success(new LoginXRequest(
            email: JsonHelper::nullableString($data, 'email'),
            password: JsonHelper::nullableString($data, 'password'),
        ));
    }

    /**
     * {@inheritDoc}
     *
     * @param  ByteBuffer  $buffer  A buffer produced against the same schema.
     * @return Result<LoginXRequest> The hydrated message.
     *
     * @copyright 2026 Tachyon
     *
     * @api
     */
    public function fromFlatbuffer(ByteBuffer $buffer): Result
    {
        $table = LoginRequest::getRootAsLoginRequest($buffer);

        return Result::success(new LoginXRequest(
            email: $table->getEmail(),
            password: $table->getPassword(),
        ));
    }
}
