<?php

declare(strict_types=1);

use App\Commands\Container\CreateContainerCommand;
use App\Services\Interno\CreateContainerUseCase;
use Domain\Enums\ContainerStatus;
use Infra\Repository\IContainerRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The create spine, over the real {@see \Domain\TableModules\Interno\ContainerTM}.
 *
 * The state a new container starts in is the table module's decision and not the
 * caller's — the command carries no status field at all — so the happy path
 * asserts it, because a container created already `sealed` could never be
 * loaded.
 */
describe('CreateContainerUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->containerTM = domain()->containerTM();
        $this->registrar = registrar();
        $this->command = new CreateContainerCommand(caller('container:create'), 'BOX-1', 100.0);
    });

    it('commits and answers a container that starts empty', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('insert')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Container)
            ->andReturn(Result::void());

        $useCase = new CreateContainerUseCase(commitsOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->code)->toBe('BOX-1')
            ->and($result->getValue()->currentWeight)->toBe(0.0)
            ->and($result->getValue()->status)->toBe(ContainerStatus::Empty);
    });

    it('rolls back when the table module rejects the input, without inserting', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute(new CreateContainerCommand(
            caller('container:create'), '', -1.0,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back and never invalidates when the insert fails', function () {
        $failure = anError();

        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldReceive('insert')->once()->andReturn(Result::failure($failure));

        // Nothing was committed, so nothing may be dropped from the cache.
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateContainerUseCase(rollsBackOnce(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($failure);
    });

    it('refuses a caller without the permission before touching anything', function () {
        $containers = Mockery::mock(IContainerRepository::class);
        $containers->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateContainerUseCase(untouchedUnitOfWork(), $views, $containers,
            $this->containerTM, $this->registrar);

        $result = $useCase->execute(new CreateContainerCommand(stranger(), 'BOX-1', 100.0));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
