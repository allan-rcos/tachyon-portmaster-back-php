<?php

declare(strict_types=1);

use App\Commands\Manifest\LoadItemCommand;
use App\Context\RoleContext;
use App\Context\UserContext;
use App\Services\Interno\LoadItemUseCase;
use App\Services\Interno\RegisterPermissionUseCase;
use Domain\Enums\ContainerStatus;
use Domain\Enums\RiskClass;
use Domain\Models\Internal\Container;
use Domain\Models\Internal\Product;
use Domain\TableModules\Interno\ManifestTM;
use Domain\TableModules\Interno\PermissionTM;
use Infra\Database\IUnitOfWork;
use Tests\Doubles\InMemoryPermissionRepository;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

describe('LoadItemUseCase orchestration', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = new RegisterPermissionUseCase(new PermissionTM(), new InMemoryPermissionRepository());
        $this->manifestTM = new ManifestTM();

        $this->container = new Container('C1', 'BOX-1', 0.0, 100.0, ContainerStatus::Empty);
        $this->product = new Product('P1', 'Diesel', 1.0, RiskClass::Class3FlammableLiquids);

        $this->command = new LoadItemCommand(
            context: new UserContext('U1', 'Allan', 'a@example.com', [
                new RoleContext('R1', 'Operator', ['manifest:load']),
            ]),
            containerId: 'C1',
            productId: 'P1',
            quantity: 30.0,
        );
    });

    it('persists the whole change and commits on the happy path', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->container));
        $containers->shouldReceive('update')->once()->andReturn(Result::void());

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::success(null));
        $manifest->shouldReceive('upsertCargo')->once()->andReturn(Result::void());
        $manifest->shouldReceive('insertTelemetry')->once()->andReturn(Result::void());

        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
        $unitOfWork->shouldReceive('commit')->once()->andReturn(Result::void());
        $unitOfWork->shouldNotReceive('rollback');

        $useCase = new LoadItemUseCase(
            $unitOfWork, $containers, $products, $manifest, $this->manifestTM, $this->registrar,
        );

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->status)->toBe(ContainerStatus::Loading)
            ->and($result->getValue()->currentWeight)->toBe(30.0);
    });

    it('rolls back when the container is not found, without persisting anything', function () {
        $notFound = Leaf::newError(new LeafContext('no such container', code: 404));

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::failure($notFound));
        $containers->shouldNotReceive('update');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldNotReceive('findById');

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldNotReceive('upsertCargo');
        $manifest->shouldNotReceive('insertTelemetry');

        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
        $unitOfWork->shouldReceive('rollback')->once()->andReturn(Result::void());
        $unitOfWork->shouldNotReceive('commit');

        $useCase = new LoadItemUseCase(
            $unitOfWork, $containers, $products, $manifest, $this->manifestTM, $this->registrar,
        );

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($notFound);
    });

    it('rolls back with 409 when the manifest rule rejects the load', function () {
        // Sealed container → ManifestTM.load returns 409 before any persistence.
        $sealed = new Container('C1', 'BOX-1', 50.0, 100.0, ContainerStatus::Sealed);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($sealed));
        $containers->shouldNotReceive('update');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::success(null));
        $manifest->shouldNotReceive('upsertCargo');

        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
        $unitOfWork->shouldReceive('rollback')->once()->andReturn(Result::void());
        $unitOfWork->shouldNotReceive('commit');

        $useCase = new LoadItemUseCase(
            $unitOfWork, $containers, $products, $manifest, $this->manifestTM, $this->registrar,
        );

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(409);
    });
})->group('App', 'UseCase');
