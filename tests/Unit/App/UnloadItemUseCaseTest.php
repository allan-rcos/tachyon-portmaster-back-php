<?php

declare(strict_types=1);

use App\Commands\Manifest\UnloadItemCommand;
use App\Services\Interno\UnloadItemUseCase;
use Domain\Enums\ContainerStatus;
use Domain\Enums\RiskClass;
use Domain\Models\Internal\Container;
use Domain\Models\Internal\ManifestCargo;
use Domain\Models\Internal\Product;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IManifestRepository;
use Infra\Repository\IProductRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The mirror of {@see \Tests\Unit\App\LoadItemUseCaseTest}, and the harder half:
 * an unload can leave the container in either of two states, and the table
 * module picks which.
 *
 * A partial unload keeps the line and stays `loading`; taking the last of the
 * cargo empties the container, which wipes the whole manifest and returns it to
 * `empty`. The two land on different repository calls — `upsertCargo` against
 * `clearManifest` — so which one is invoked is the assertion that the use case
 * carried the table module's decision through rather than assuming one.
 */
describe('UnloadItemUseCase orchestration', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->manifestTM = domain()->manifestTM();
        $this->registrar = registrar();

        $this->product = new Product('P1', 'Diesel', 1.0, RiskClass::Class3FlammableLiquids);
        $this->container = new Container('C1', 'BOX-1', 30.0, 100.0, ContainerStatus::Loading);
        $this->cargo = new ManifestCargo('C1', 'P1', 30.0, 30.0);
    });

    it('keeps the container loading on a partial unload', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->container));
        $containers->shouldReceive('update')->once()->andReturn(Result::void());

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::success($this->cargo));
        $manifest->shouldReceive('upsertCargo')->once()->andReturn(Result::void());
        $manifest->shouldReceive('insertTelemetry')->once()->andReturn(Result::void());
        $manifest->shouldNotReceive('clearManifest');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Container)
            ->andReturn(Result::void());

        $useCase = new UnloadItemUseCase(commitsOnce(), $views, $containers, $products, $manifest,
            $this->manifestTM, $this->registrar);

        $result = $useCase->execute(new UnloadItemCommand(
            caller('manifest:unload'), 'C1', 'P1', 10.0,
        ));

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->currentWeight)->toBe(20.0)
            ->and($result->getValue()->status)->toBe(ContainerStatus::Loading);
    });

    it('empties the container and wipes the manifest when the last cargo leaves', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->container));
        $containers->shouldReceive('update')->once()->andReturn(Result::void());

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::success($this->cargo));
        // The whole manifest goes, not just this product's line: an empty
        // container holding manifest rows is a state nothing could explain.
        $manifest->shouldReceive('clearManifest')->once()->with('C1')->andReturn(Result::void());
        $manifest->shouldNotReceive('upsertCargo');
        $manifest->shouldReceive('insertTelemetry')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->andReturn(Result::void());

        $useCase = new UnloadItemUseCase(commitsOnce(), $views, $containers, $products, $manifest,
            $this->manifestTM, $this->registrar);

        $result = $useCase->execute(new UnloadItemCommand(
            caller('manifest:unload'), 'C1', 'P1', 30.0,
        ));

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->currentWeight)->toBe(0.0)
            ->and($result->getValue()->status)->toBe(ContainerStatus::Empty);
    });

    it('rolls back with 409 when nothing of that product is loaded', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->container));
        $containers->shouldNotReceive('update');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        // No line at all is the ordinary state of a product that was never
        // loaded here, so the lookup succeeds carrying nothing.
        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::void());
        $manifest->shouldNotReceive('upsertCargo');
        $manifest->shouldNotReceive('clearManifest');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UnloadItemUseCase(rollsBackOnce(), $views, $containers, $products, $manifest,
            $this->manifestTM, $this->registrar);

        $result = $useCase->execute(new UnloadItemCommand(
            caller('manifest:unload'), 'C1', 'P1', 10.0,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('rolls back with 409 when the container is already sealed', function () {
        $sealed = new Container('C1', 'BOX-1', 30.0, 100.0, ContainerStatus::Sealed);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($sealed));
        $containers->shouldNotReceive('update');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::success($this->cargo));
        $manifest->shouldNotReceive('upsertCargo');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UnloadItemUseCase(rollsBackOnce(), $views, $containers, $products, $manifest,
            $this->manifestTM, $this->registrar);

        $result = $useCase->execute(new UnloadItemCommand(
            caller('manifest:unload'), 'C1', 'P1', 10.0,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('rolls back and never invalidates when the manifest write fails', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->container));
        $containers->shouldReceive('update')->once()->andReturn(Result::void());

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->product));

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldReceive('findCargo')->once()->andReturn(Result::success($this->cargo));
        $manifest->shouldReceive('upsertCargo')->once()->andReturn(Result::failure(anError()));
        $manifest->shouldNotReceive('insertTelemetry');

        // The container update already went through, which is exactly why the
        // rollback matters here: without it the container would be lighter than
        // its own manifest says.
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UnloadItemUseCase(rollsBackOnce(), $views, $containers, $products, $manifest,
            $this->manifestTM, $this->registrar);

        $result = $useCase->execute(new UnloadItemCommand(
            caller('manifest:unload'), 'C1', 'P1', 10.0,
        ));

        expect($result->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('findById');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldNotReceive('findById');

        $manifest = Mockery::mock(IManifestRepository::class);
        $manifest->shouldNotReceive('findCargo');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UnloadItemUseCase(untouchedUnitOfWork(), $views, $containers, $products,
            $manifest, $this->manifestTM, $this->registrar);

        $result = $useCase->execute(new UnloadItemCommand(stranger(), 'C1', 'P1', 10.0));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
