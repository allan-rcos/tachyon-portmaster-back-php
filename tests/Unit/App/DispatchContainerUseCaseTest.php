<?php

declare(strict_types=1);

use App\Commands\Container\DispatchContainerCommand;
use App\Services\Interno\DispatchContainerUseCase;
use Domain\Enums\ContainerStatus;
use Domain\Models\Internal\Container;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The last transition, and the only one with no way back: a dispatched container
 * has left.
 *
 * Its single rule is that the seal came first, so the refusal is asserted from
 * the loading state — the one a caller would most plausibly try to skip from.
 */
describe('DispatchContainerUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->containerTM = domain()->containerTM();
        $this->registrar = registrar();
        $this->sealed = new Container('C1', 'BOX-1', 50.0, 100.0, ContainerStatus::Sealed);
        $this->command = new DispatchContainerCommand(caller('container:dispatch'), 'C1');
    });

    it('commits a sealed container into transit', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->sealed));
        $containers->shouldReceive('update')->once()
            ->with(Mockery::on(fn ($c) => $c->status === ContainerStatus::InTransit))
            ->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Container)
            ->andReturn(Result::void());

        $useCase = new DispatchContainerUseCase(commitsOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('rolls back with 409 when the container was never sealed', function () {
        $loading = new Container('C1', 'BOX-1', 50.0, 100.0, ContainerStatus::Loading);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($loading));
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DispatchContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('rolls back when the container does not exist', function () {
        $missing = anError(404, 'no such container');

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DispatchContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back and never invalidates when the write fails', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->sealed));
        $containers->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DispatchContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('findById');
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DispatchContainerUseCase(untouchedUnitOfWork(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute(new DispatchContainerCommand(stranger(), 'C1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
