<?php

/**
 * Manifest Response Factory.
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

use API\Fbs\Manifest\ManifestResponse;
use API\Negociation\DTO\Container\ContainerXResponseFactory;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ManifestXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ManifestXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var IResponseAbstractFactory|null The nested container, wrapped once — a
     *                                    message is rendered as often as it is
     *                                    asked for, its factory built only here.
     */
    private ?IResponseAbstractFactory $containerFactory;

    /**
     * @param  ManifestXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ManifestXResponse $message)
    {
        $this->containerFactory = $message->container !== null
            ? new ContainerXResponseFactory($message->container)
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
        $container = 0;
        if ($this->containerFactory !== null) {
            $container = $this->containerFactory->createFlatbuffer($builder)->getValue();
        }

        $message = $this->message->message !== null
            ? $builder->createString($this->message->message)
            : 0;

        return Result::success(ManifestResponse::createManifestResponse($builder, $message, $container));
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
            'message'   => $this->message->message,
            'container' => $this->containerFactory !== null ? $this->containerFactory->createJson()->getValue() : null,
        ]);
    }
}
