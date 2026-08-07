<?php

/**
 * Metadata Item Response Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Metadata;

use API\Fbs\Metadata\MetadataItemResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see MetadataItemXResponse} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class MetadataItemXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @param  MetadataItemXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private MetadataItemXResponse $message)
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
        $slug = $builder->createString($this->message->slug);

        return Result::success(MetadataItemResponse::createMetadataItemResponse($builder, $this->message->id, $slug));
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
            'id'   => $this->message->id,
            'slug' => $this->message->slug,
        ]);
    }
}
