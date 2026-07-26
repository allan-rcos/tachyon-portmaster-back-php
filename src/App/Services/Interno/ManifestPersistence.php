<?php

declare(strict_types=1);

namespace App\Services\Interno;

use Domain\Models\IContainer;
use Domain\Models\IManifestChange;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Shared\Exceptions\Result;

/**
 * Persists a {@see IManifestChange} (already computed by the manifest TM) and
 * commits: the container's new state, the cargo mutation, and the telemetry
 * entry. Shared by the load and unload use cases.
 */
final readonly class ManifestPersistence
{
    /**
     * @return Result<IContainer> The persisted container on success.
     */
    public static function commit(
        IUnitOfWork $unitOfWork,
        IContainerRepository $containers,
        IManifestRepository $manifest,
        IManifestChange $change,
    ): Result {
        $updated = $containers->update($change->container);
        if (!$updated->isSuccess()) {
            $unitOfWork->rollback();

            return Result::failure($updated->getErrorId());
        }

        if ($change->clearManifest) {
            $cargo = $manifest->clearManifest($change->container->id);
        } elseif ($change->cargo === null) {
            $cargo = $manifest->deleteCargo($change->container->id, $change->productId);
        } else {
            $cargo = $manifest->upsertCargo($change->cargo);
        }
        if (!$cargo->isSuccess()) {
            $unitOfWork->rollback();

            return Result::failure($cargo->getErrorId());
        }

        $telemetry = $manifest->insertTelemetry(
            $change->container->id,
            $change->event,
            'Product ' . $change->productId,
        );
        if (!$telemetry->isSuccess()) {
            $unitOfWork->rollback();

            return Result::failure($telemetry->getErrorId());
        }

        $commit = $unitOfWork->commit();
        if (!$commit->isSuccess()) {
            return Result::failure($commit->getErrorId());
        }

        return Result::success($change->container);
    }
}
