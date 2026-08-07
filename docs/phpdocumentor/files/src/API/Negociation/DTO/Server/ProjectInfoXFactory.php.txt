<?php

/**
 * Project Info Factory.
 *
 * @category API
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 *
 * @filesource
 */

declare(strict_types=1);

namespace API\Negociation\DTO\Server;

use API\Fbs\Server\ProjectInfo;
use API\Negociation\IResponseAbstractFactory;
use Google\FlatBuffers\FlatbufferBuilder;
use Shared\Exceptions\Result;

/**
 * Renders a {@see ProjectInfoX} in either wire format.
 *
 * @see \API\Negociation\DTO\Auth\UserXFactory The outbound factory shape this follows.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */
final readonly class ProjectInfoXFactory implements IResponseAbstractFactory
{
    /**
     * @param  ProjectInfoX  $message  The message to render.
     *
     * @copyright 2026 Tachyon
     */
    public function __construct(private ProjectInfoX $message)
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
        $name        = $this->message->name !== null ? $builder->createString($this->message->name) : 0;
        $version     = $this->message->version !== null ? $builder->createString($this->message->version) : 0;
        $environment = $this->message->environment !== null ? $builder->createString($this->message->environment) : 0;
        $runtime     = $this->message->runtime !== null ? $builder->createString($this->message->runtime) : 0;

        return Result::success(ProjectInfo::createProjectInfo(
            $builder,
            $name,
            $version,
            $environment,
            $runtime,
            $this->message->memoryUsageMb,
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
            'name'            => $this->message->name,
            'version'         => $this->message->version,
            'environment'     => $this->message->environment,
            'runtime'         => $this->message->runtime,
            'memory_usage_mb' => $this->message->memoryUsageMb,
        ]);
    }
}
