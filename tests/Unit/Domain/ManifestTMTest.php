<?php

declare(strict_types=1);

use Domain\Enums\ContainerStatus;
use Domain\Enums\TelemetryEvent;
use Domain\Enums\RiskClass;
use Domain\Models\IManifestChange;
use Domain\Models\Internal\Container;
use Domain\Models\Internal\ManifestCargo;
use Domain\Models\Internal\Product;
use Domain\TableModules\Interno\ManifestTM;
use Shared\Exceptions\Leaf;

/** Density 1.0 keeps weight equal to quantity for readable arithmetic. */
function mtProduct(float $density = 1.0): Product
{
    return new Product('P1', 'Diesel', $density, RiskClass::Class3FlammableLiquids);
}

function mtContainer(
    ContainerStatus $status = ContainerStatus::Empty,
    float $weight = 0.0,
    float $capacity = 100.0,
): Container {
    return new Container('C1', 'BOX-1', $weight, $capacity, $status);
}

describe('ManifestTM load', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->tm = new ManifestTM();
    });

    it('loads into an empty container: weight, Loading status, load event', function () {
        $result = $this->tm->load(mtContainer(), mtProduct(), 30.0, null);

        expect($result->isSuccess())->toBeTrue();

        /** @var IManifestChange $change */
        $change = $result->getValue();

        expect($change->container->status)->toBe(ContainerStatus::Loading)
            ->and($change->container->currentWeight)->toBe(30.0)
            ->and($change->clearManifest)->toBeFalse()
            ->and($change->event)->toBe(TelemetryEvent::Load)
            ->and($change->cargo?->quantity)->toBe(30.0)
            ->and($change->cargo?->weight)->toBe(30.0);
    });

    it('adds onto an existing cargo line', function () {
        $current = new ManifestCargo('C1', 'P1', 10.0, 10.0);

        $change = $this->tm->load(mtContainer(ContainerStatus::Loading, 10.0), mtProduct(), 5.0, $current)->getValue();

        expect($change->cargo?->quantity)->toBe(15.0)
            ->and($change->cargo?->weight)->toBe(15.0)
            ->and($change->container->currentWeight)->toBe(15.0);
    });

    it('rejects a non-positive quantity with 422', function () {
        $result = $this->tm->load(mtContainer(), mtProduct(), 0.0, null);

        expect(Leaf::getError($result->getErrorId())?->code)->toBe(422);
    });

    it('rejects loading a sealed or dispatched container with 409', function (ContainerStatus $status) {
        $result = $this->tm->load(mtContainer($status, 20.0), mtProduct(), 5.0, null);

        expect(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    })->with([
        'sealed' => ContainerStatus::Sealed,
        'in transit' => ContainerStatus::InTransit,
    ]);

    it('rejects a load that would exceed capacity with 409', function () {
        $result = $this->tm->load(mtContainer(ContainerStatus::Loading, 90.0, 100.0), mtProduct(), 20.0, null);

        expect(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    });
})->group('Domain', 'TableModule');

describe('ManifestTM unload', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->tm = new ManifestTM();
    });

    it('rejects unloading a container that is not loading with 409', function () {
        $result = $this->tm->unload(mtContainer(ContainerStatus::Empty), mtProduct(), 5.0, null);

        expect(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    });

    it('rejects unloading more than is loaded with 409', function () {
        $current = new ManifestCargo('C1', 'P1', 5.0, 5.0);
        $result = $this->tm->unload(mtContainer(ContainerStatus::Loading, 5.0), mtProduct(), 10.0, $current);

        expect(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    });

    it('rejects a non-positive quantity with 422', function () {
        $result = $this->tm->unload(mtContainer(ContainerStatus::Loading, 5.0), mtProduct(), -1.0, null);

        expect(Leaf::getError($result->getErrorId())?->code)->toBe(422);
    });

    it('empties the container: back to Empty and clears the whole manifest', function () {
        $current = new ManifestCargo('C1', 'P1', 20.0, 20.0);

        $change = $this->tm->unload(mtContainer(ContainerStatus::Loading, 20.0), mtProduct(), 20.0, $current)->getValue();

        expect($change->container->status)->toBe(ContainerStatus::Empty)
            ->and($change->container->currentWeight)->toBe(0.0)
            ->and($change->clearManifest)->toBeTrue()
            ->and($change->cargo)->toBeNull()
            ->and($change->event)->toBe(TelemetryEvent::Unload);
    });

    it('drops a fully-unloaded product line while the container stays loading', function () {
        // Two products loaded (weight 30); unloading all 10 of P1 leaves 20 → still loading.
        $current = new ManifestCargo('C1', 'P1', 10.0, 10.0);

        $change = $this->tm->unload(mtContainer(ContainerStatus::Loading, 30.0), mtProduct(), 10.0, $current)->getValue();

        expect($change->clearManifest)->toBeFalse()
            ->and($change->cargo)->toBeNull()
            ->and($change->container->status)->toBe(ContainerStatus::Loading)
            ->and($change->container->currentWeight)->toBe(20.0);
    });

    it('partially reduces a cargo line', function () {
        $current = new ManifestCargo('C1', 'P1', 10.0, 10.0);

        $change = $this->tm->unload(mtContainer(ContainerStatus::Loading, 30.0), mtProduct(), 4.0, $current)->getValue();

        expect($change->cargo?->quantity)->toBe(6.0)
            ->and($change->cargo?->weight)->toBe(6.0)
            ->and($change->container->currentWeight)->toBe(26.0)
            ->and($change->container->status)->toBe(ContainerStatus::Loading);
    });
})->group('Domain', 'TableModule');
