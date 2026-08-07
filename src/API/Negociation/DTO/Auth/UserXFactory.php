<?php

/**
 * User Factory.
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

use API\Fbs\Auth\User;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see UserX} in either wire format.
 *
 * The shape every outbound factory follows, written out once here: the message
 * comes in through the constructor, `createJson()` maps it onto the schema's
 * field names, and `createFlatbuffer()` writes it into the builder it is
 * handed. Nothing else — a factory this simple is the whole contract.
 *
 * @see IResponseAbstractFactory The contract.
 * @see User The generated table this writes.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class UserXFactory implements IResponseAbstractFactory
{
    /**
     * @param  UserX  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private UserX $message)
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
        $id    = $this->message->id !== null ? $builder->createString($this->message->id) : 0;
        $name  = $this->message->name !== null ? $builder->createString($this->message->name) : 0;
        $email = $this->message->email !== null ? $builder->createString($this->message->email) : 0;

        return Result::success(User::createUser($builder, $id, $name, $email));
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
            'id'    => $this->message->id,
            'name'  => $this->message->name,
            'email' => $this->message->email,
        ]);
    }
}
