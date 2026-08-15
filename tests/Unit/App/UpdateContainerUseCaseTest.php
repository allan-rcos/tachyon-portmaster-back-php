<?php

declare(strict_types=1);

use App\Commands\Container\UpdateContainerCommand;
use App\Services\Interno\UpdateContainerUseCase;
use Domain\Enums\ContainerStatus;
use Domain\Models\Internal\Container;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * Only the capacity is updatable, and the load already in the container is not
 * the caller's to rewrite.
 *
 * That is why the table module is handed the loaded container rather than the
 * command: it rebuilds around the existing weight and status, and the happy path
 * asserts both survived, because a capacity edit that silently reset the weight
 * to zero would leave the manifest and the container disagreeing.
 */
describe('UpdateContainerUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->containerTM = domain()->containerTM();
        $this->registrar = registrar();
        $this->existing = new Container('C1', 'BOX-1', 30.0, 100.0, ContainerStatus::Loading);
        $this->command = new UpdateContainerCommand(caller('container:update'), 'C1', 200.0);
    });

    it('commits, keeping the current weight and status', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->with('C1')
            ->andReturn(Result::success($this->existing));
        $containers->shouldReceive('update')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Container)
            ->andReturn(Result::void());

        $useCase = new UpdateContainerUseCase(commitsOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->maxCapacity)->toBe(200.0)
            ->and($result->getValue()->currentWeight)->toBe(30.0)
            ->and($result->getValue()->status)->toBe(ContainerStatus::Loading);
    });

    it('rolls back and never updates when the container does not exist', function () {
        $missing = anError(404, 'no such container');

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back when the table module rejects the new capacity', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute(new UpdateContainerCommand(
            caller('container:update'), 'C1', -1.0,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back and never invalidates when the write fails', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $containers->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('findById');
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateContainerUseCase(untouchedUnitOfWork(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute(new UpdateContainerCommand(stranger(), 'C1', 200.0));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
