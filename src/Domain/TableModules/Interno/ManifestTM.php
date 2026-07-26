<?php

namespace Domain\TableModules\Interno;

use Domain\Enums\ContainerStatus;
use Domain\Models\IContainer;
use Domain\Models\IManifestCargo;
use Domain\Models\Internal\Container;
use Domain\Models\Internal\ManifestCargo;
use Domain\Models\Internal\ManifestChange;
use Domain\Models\IProduct;
use Domain\TableModules\IManifestTM;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

readonly final class ManifestTM implements IManifestTM
{
    private const float EPSILON = 0.0000001;

    /**
     * Telemetry event slugs this module emits.
     *
     * Local constants rather than a shared enum: telemetry events are system
     * metadata now ({@see \Domain\Models\ITelemetryEvent}), registered at boot
     * by the application layer. A central enum would put the domain back in
     * charge of enumerating them.
     */
    private const string EVENT_LOAD = 'load';
    private const string EVENT_UNLOAD = 'unload';

    public function load(IContainer $container, IProduct $product, float $quantity, ?IManifestCargo $current): Result
    {
        if ($quantity <= 0) {
            return $this->fail('Quantity must be greater than zero.', 422);
        }

        if ($container->status === ContainerStatus::Sealed || $container->status === ContainerStatus::InTransit) {
            return $this->fail('Cannot load a sealed or dispatched container.', 409);
        }

        $itemWeight = $product->density * $quantity;
        $newContainerWeight = $container->currentWeight + $itemWeight;

        if ($newContainerWeight > $container->maxCapacity + self::EPSILON) {
            return $this->fail('Loading this item would exceed the container capacity.', 409);
        }

        $existingQuantity = $current !== null ? $current->quantity : 0.0;
        $existingWeight = $current !== null ? $current->weight : 0.0;

        $cargo = new ManifestCargo(
            containerId: $container->id,
            productId: $product->id,
            quantity: $existingQuantity + $quantity,
            weight: $existingWeight + $itemWeight,
        );

        return Result::success(new ManifestChange(
            container: $this->withWeightAndStatus($container, $newContainerWeight, ContainerStatus::Loading),
            productId: $product->id,
            cargo: $cargo,
            clearManifest: false,
            event: self::EVENT_LOAD,
        ));
    }

    public function unload(IContainer $container, IProduct $product, float $quantity, ?IManifestCargo $current): Result
    {
        if ($quantity <= 0) {
            return $this->fail('Quantity must be greater than zero.', 422);
        }

        if ($container->status !== ContainerStatus::Loading) {
            return $this->fail('Only a container in the loading state can be unloaded.', 409);
        }

        if ($current === null || $current->quantity + self::EPSILON < $quantity) {
            return $this->fail('Not enough of this product is loaded to unload the requested quantity.', 409);
        }

        $itemWeight = $product->density * $quantity;
        $newContainerWeight = max(0.0, $container->currentWeight - $itemWeight);
        $newCargoQuantity = $current->quantity - $quantity;
        $newCargoWeight = max(0.0, $current->weight - $itemWeight);

        // Container emptied → back to Empty, wipe the whole manifest.
        if ($newContainerWeight <= self::EPSILON) {
            return Result::success(new ManifestChange(
                container: $this->withWeightAndStatus($container, 0.0, ContainerStatus::Empty),
                productId: $product->id,
                cargo: null,
                clearManifest: true,
                event: self::EVENT_UNLOAD,
            ));
        }

        // This product fully unloaded (but container not empty) → drop its line.
        $cargo = $newCargoQuantity <= self::EPSILON
            ? null
            : new ManifestCargo($container->id, $product->id, $newCargoQuantity, $newCargoWeight);

        return Result::success(new ManifestChange(
            container: $this->withWeightAndStatus($container, $newContainerWeight, ContainerStatus::Loading),
            productId: $product->id,
            cargo: $cargo,
            clearManifest: false,
            event: self::EVENT_UNLOAD,
        ));
    }

    private function withWeightAndStatus(IContainer $container, float $weight, ContainerStatus $status): Container
    {
        return new Container(
            id: $container->id,
            code: $container->code,
            currentWeight: $weight,
            maxCapacity: $container->maxCapacity,
            status: $status,
        );
    }

    /**
     * @return Result<never>
     */
    private function fail(string $message, int $code): Result
    {
        return Result::failure(Leaf::newError(new LeafContext($message, code: $code)));
    }
}
