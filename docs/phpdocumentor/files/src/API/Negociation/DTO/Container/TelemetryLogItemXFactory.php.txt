<?php

/**
 * Telemetry Log Item Factory.
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

use API\Fbs\Container\TelemetryLogItem;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see TelemetryLogItemX} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class TelemetryLogItemXFactory implements IResponseAbstractFactory
{
    /**
     * @param  TelemetryLogItemX  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private TelemetryLogItemX $message)
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
        $id = $this->message->id !== null ? $builder->createString($this->message->id) : 0;
        $description = $this->message->description !== null
            ? $builder->createString($this->message->description)
            : 0;
        $timestamp = $this->message->timestamp !== null
            ? $builder->createString($this->message->timestamp)
            : 0;

        return Result::success(TelemetryLogItem::createTelemetryLogItem(
            $builder,
            $id,
            $this->message->event->toInt(),
            $description,
            $timestamp,
        ));
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
            'id'          => $this->message->id,
            'event'       => $this->message->event->value,
            'description' => $this->message->description,
            'timestamp'   => $this->message->timestamp,
        ]);
    }
}
