<?php

declare(strict_types=1);

use Domain\Enums\ContainerStatus;
use Domain\ID\IDatabaseIdGenerator;
use Domain\Models\IContainer;
use Domain\Models\Internal\Container;
use Domain\TableModules\Interno\ContainerTM;
use Shared\Exceptions\Leaf;

/**
 * @param  float  $weight
 */
function containerFixture(
    ContainerStatus $status = ContainerStatus::Empty,
    float $weight = 0.0,
    float $capacity = 100.0,
): Container {
    return new Container('C1', 'BOX-1', $weight, $capacity, $status);
}

describe('ContainerTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $ids = Mockery::mock(IDatabaseIdGenerator::class);
        $ids->shouldReceive('generate')->andReturn('CONT1');
        $this->tm = new ContainerTM($ids);
    });

    it('creates an empty container with zero weight', function () {
        $result = $this->tm->create('BOX-9', 500.0);

        expect($result->isSuccess())->toBeTrue();

        /** @var IContainer $container */
        $container = $result->getValue();

        expect($container->id)->toBe('CONT1')
            ->and($container->status)->toBe(ContainerStatus::Empty)
            ->and($container->currentWeight)->toBe(0.0)
            ->and($container->maxCapacity)->toBe(500.0);
    });

    it('rejects create with blank code or non-positive capacity, reporting both', function () {
        $result = $this->tm->create('', 0.0);

        $details = Leaf::getError($result->getErrorId())?->details;

        expect($result->isSuccess())->toBeFalse()
            ->and($details?->hasKey('code'))->toBeTrue()
            ->and($details?->hasKey('max_capacity'))->toBeTrue();
    });

    it('seals a loading container that is at least 10% full', function () {
        $result = $this->tm->seal(containerFixture(ContainerStatus::Loading, 10.0, 100.0));

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->status)->toBe(ContainerStatus::Sealed);
    });

    it('refuses to seal a container that is not loading', function (ContainerStatus $status) {
        $result = $this->tm->seal(containerFixture($status, 50.0, 100.0));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    })->with([
        'empty' => ContainerStatus::Empty,
        'sealed' => ContainerStatus::Sealed,
        'in transit' => ContainerStatus::InTransit,
    ]);

    it('refuses to seal a loading container below the 10% fill threshold', function () {
        $result = $this->tm->seal(containerFixture(ContainerStatus::Loading, 5.0, 100.0));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    });

    it('dispatches only a sealed container', function () {
        $ok = $this->tm->dispatch(containerFixture(ContainerStatus::Sealed, 50.0));
        $bad = $this->tm->dispatch(containerFixture(ContainerStatus::Loading, 50.0));

        expect($ok->isSuccess())->toBeTrue()
            ->and($ok->getValue()->status)->toBe(ContainerStatus::InTransit)
            ->and($bad->isSuccess())->toBeFalse()
            ->and(Leaf::getError($bad->getErrorId())?->code)->toBe(409);
    });

    it('preserves id, code, weight and status when updating capacity', function () {
        $result = $this->tm->update(containerFixture(ContainerStatus::Loading, 40.0, 100.0), 250.0);

        expect($result->isSuccess())->toBeTrue();
        $updated = $result->getValue();

        expect($updated->id)->toBe('C1')
            ->and($updated->code)->toBe('BOX-1')
            ->and($updated->currentWeight)->toBe(40.0)
            ->and($updated->status)->toBe(ContainerStatus::Loading)
            ->and($updated->maxCapacity)->toBe(250.0);
    });
})->group('Domain', 'TableModule');
