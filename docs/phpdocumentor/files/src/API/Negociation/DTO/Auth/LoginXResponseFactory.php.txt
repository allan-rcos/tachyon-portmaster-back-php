<?php

/**
 * Login Response Factory.
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

use API\Fbs\Auth\LoginResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see LoginXResponse} in either wire format.
 *
 * The smallest example of nesting: the child factory is built once, in the
 * constructor, and its table is written into *this* builder — through
 * {@see IResponseAbstractFactory}, and before `createLoginResponse()` starts
 * the parent table, which is the order FlatBuffers requires.
 *
 * @see UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class LoginXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var IResponseAbstractFactory|null The nested user, wrapped once — a
     *                                    message is rendered as often as it is
     *                                    asked for, its factory built only here.
     */
    private ?IResponseAbstractFactory $userFactory;

    /**
     * @param  LoginXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private LoginXResponse $message)
    {
        $this->userFactory = $message->user !== null
            ? new UserXFactory($message->user)
            : null;
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
        $user = 0;
        if ($this->userFactory !== null) {
            $user = $this->userFactory->createFlatbuffer($builder)->getValue();
        }

        $token     = $this->message->token !== null ? $builder->createString($this->message->token) : 0;
        $tokenType = $this->message->tokenType !== null ? $builder->createString($this->message->tokenType) : 0;

        return Result::success(LoginResponse::createLoginResponse($builder, $token, $tokenType, $user));
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
            'token'      => $this->message->token,
            'token_type' => $this->message->tokenType,
            'user'       => $this->userFactory !== null ? $this->userFactory->createJson()->getValue() : null,
        ]);
    }
}
