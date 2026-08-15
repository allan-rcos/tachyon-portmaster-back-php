<?php

declare(strict_types=1);

use App\Commands\Container\DeleteContainerCommand;
use App\Services\Interno\DeleteContainerUseCase;
use Domain\Enums\ContainerStatus;
use Domain\Models\Internal\Container;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The delete spine: load to know it is there, delete, commit, then drop the
 * cache group.
 *
 * The load exists because the repository's delete is a no-op on a row that is
 * not there — without it a delete of something that never existed would answer
 * 204 and lie.
 */
describe('DeleteContainerUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->existing = new Container('C1', 'BOX-1', 0.0, 100.0, ContainerStatus::Empty);
        $this->command = new DeleteContainerCommand(caller('container:delete'), 'C1');
    });

    it('commits and answers a void success', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->with('C1')
            ->andReturn(Result::success($this->existing));
        $containers->shouldReceive('delete')->once()->with('C1')->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Container)
            ->andReturn(Result::void());

        $useCase = new DeleteContainerUseCase(commitsOnce(), $views, $containers, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('surfaces the 404 and never deletes when the container is not there', function () {
        $missing = anError(404, 'no such container');

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $containers->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteContainerUseCase(rollsBackOnce(), $views, $containers, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back and never invalidates when the delete fails', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $containers->shouldReceive('delete')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteContainerUseCase(rollsBackOnce(), $views, $containers, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('findById');
        $containers->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteContainerUseCase(untouchedUnitOfWork(), $views, $containers,
            $this->registrar);

        $result = $useCase->execute(new DeleteContainerCommand(stranger(), 'C1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
