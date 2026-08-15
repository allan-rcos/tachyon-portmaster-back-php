<?php

declare(strict_types=1);

use App\Commands\Container\SealContainerCommand;
use App\Services\Interno\SealContainerUseCase;
use Domain\Enums\ContainerStatus;
use Domain\Models\Internal\Container;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * A transition, not an edit: what the caller supplies is an id, and whether the
 * move is legal is entirely the table module's answer.
 *
 * Both of its refusals are exercised through the use case rather than only in
 * {@see \Tests\Unit\Domain\ContainerTMTest}, because what is being pinned here
 * is that a 409 rolls back and never reaches the repository — a sealed container
 * written from a rejected transition would be a shipment nobody can undo.
 */
describe('SealContainerUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->containerTM = domain()->containerTM();
        $this->registrar = registrar();
        $this->command = new SealContainerCommand(caller('container:seal'), 'C1');
    });

    it('commits a loading container that is full enough', function () {
        $loading = new Container('C1', 'BOX-1', 50.0, 100.0, ContainerStatus::Loading);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($loading));
        $containers->shouldReceive('update')->once()
            ->with(Mockery::on(fn ($c) => $c->status === ContainerStatus::Sealed))
            ->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Container)
            ->andReturn(Result::void());

        $useCase = new SealContainerUseCase(commitsOnce(), $views, $containers, $this->containerTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('rolls back with 409 when the container is not loading', function () {
        $empty = new Container('C1', 'BOX-1', 0.0, 100.0, ContainerStatus::Empty);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($empty));
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new SealContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('rolls back with 409 when the container is under the fill threshold', function () {
        $barelyLoaded = new Container('C1', 'BOX-1', 5.0, 100.0, ContainerStatus::Loading);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($barelyLoaded));
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new SealContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('rolls back and never invalidates when the write fails', function () {
        $loading = new Container('C1', 'BOX-1', 50.0, 100.0, ContainerStatus::Loading);

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($loading));
        $containers->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new SealContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('findById');
        $containers->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new SealContainerUseCase(untouchedUnitOfWork(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute(new SealContainerCommand(stranger(), 'C1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
