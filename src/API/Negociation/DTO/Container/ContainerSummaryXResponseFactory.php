<?php

/**
 * Container Summary Response Factory.
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

use API\Fbs\Container\ContainerSummaryResponse;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ContainerSummaryXResponse} in either wire format.
 *
 * The deepest tree in the schema — two vectors and a child table — and the
 * clearest illustration of the ordering rule: every row of both vectors, then
 * both vectors, then the child, and only then this table.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ContainerSummaryXResponseFactory implements IResponseAbstractFactory
{
    /**
     * @var IResponseAbstractFactory|null The nested container, wrapped once — a
     *                                    message is rendered as often as it is
     *                                    asked for, its factory built only here.
     */
    private ?IResponseAbstractFactory $containerFactory;

    /**
     * @var list<IResponseAbstractFactory> One factory per manifest row, wrapped
     *                                     once, in the order the message holds
     *                                     them.
     */
    private array $manifestFactories;

    /**
     * @var list<IResponseAbstractFactory> One factory per recent logs row, wrapped
     *                                     once, in the order the message holds
     *                                     them.
     */
    private array $recentLogsFactories;

    /**
     * @param  ContainerSummaryXResponse  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ContainerSummaryXResponse $message)
    {
        $this->containerFactory = $message->container !== null
            ? new ContainerXResponseFactory($message->container)
            : null;
        $this->manifestFactories = array_map(
            static fn (CargoManifestItemX $item): IResponseAbstractFactory => new CargoManifestItemXFactory($item),
            $message->manifest,
        );
        $this->recentLogsFactories = array_map(
            static fn (TelemetryLogItemX $item): IResponseAbstractFactory => new TelemetryLogItemXFactory($item),
            $message->recentLogs,
        );
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
        $manifestOffsets = array_map(
            static fn (IResponseAbstractFactory $item): int => $item->createFlatbuffer($builder)->getValue(),
            $this->manifestFactories,
        );
        $logOffsets = array_map(
            static fn (IResponseAbstractFactory $item): int => $item->createFlatbuffer($builder)->getValue(),
            $this->recentLogsFactories,
        );

        $manifest   = ContainerSummaryResponse::createManifestVector($builder, $manifestOffsets);
        $recentLogs = ContainerSummaryResponse::createRecentLogsVector($builder, $logOffsets);

        $container = 0;
        if ($this->containerFactory !== null) {
            $container = $this->containerFactory->createFlatbuffer($builder)->getValue();
        }

        return Result::success(ContainerSummaryResponse::createContainerSummaryResponse(
            $builder,
            $container,
            $manifest,
            $recentLogs,
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
            'container' => $this->containerFactory !== null ? $this->containerFactory->createJson()->getValue() : null,
            'manifest' => array_map(
                static fn (IResponseAbstractFactory $item): array => $item->createJson()->getValue(),
                $this->manifestFactories,
            ),
            'recent_logs' => array_map(
                static fn (IResponseAbstractFactory $item): array => $item->createJson()->getValue(),
                $this->recentLogsFactories,
            ),
        ]);
    }
}
