<?php

namespace Infra\Repository;

use Domain\Models\IManifestCargo;
use Shared\Exceptions\Result;

interface IManifestRepository
{
    /**
     * @return Result<IManifestCargo|null> The product's cargo line in the container, or null.
     */
    public function findCargo(string $containerId, string $productId): Result;

    /**
     * Inserts or replaces the product's cargo line.
     * @return Result<null>
     */
    public function upsertCargo(IManifestCargo $cargo): Result;

    /**
     * Removes a single product's cargo line from a container.
     * @return Result<null>
     */
    public function deleteCargo(string $containerId, string $productId): Result;

    /**
     * Removes the container's entire manifest.
     * @return Result<null>
     */
    public function clearManifest(string $containerId): Result;

    /**
     * Appends a telemetry log entry (auto id, NOW() timestamp).
     *
     * @param  string  $event  The telemetry event slug.
     * @return Result<null>
     */
    public function insertTelemetry(string $containerId, string $event, ?string $description): Result;
}
