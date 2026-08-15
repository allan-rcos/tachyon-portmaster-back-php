<?php

declare(strict_types=1);

use App\Events\MetaEvent;
use App\Queries\Role\ListRolesQuery;
use App\Services\Interno\ListRolesUseCase;
use Ds\Seq;
use Infra\Query\Interno\ListRolesDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Role\RoleListView;
use Infra\Query\Role\RoleViewItem;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The cached-listing shape, under the `Role` group that
 * {@see \Tests\Unit\App\CreateRoleUseCaseTest} and
 * {@see \Tests\Unit\App\UpdateRolePermissionsUseCaseTest} drop on every write.
 *
 * That pairing is what keeps a permission change from being invisible for a
 * whole TTL to whoever is looking at the list.
 */
describe('ListRolesUseCase caching', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->events = events();
        $this->query = new ListRolesQuery(caller('role:list'), limit: 10);
        $this->view = new RoleListView(
            items: new Seq([new RoleViewItem('R1', 'Operator', 3, ['product:read'])]),
            nextCursor: null,
            total: 1,
        );
    });

    it('answers from the cache without querying, and reports the hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::success($this->view));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListRolesUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeTrue();
    });

    it('queries and stores on a miss, and reports no hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldReceive('put')->once()
            ->with(ViewCacheGroup::Role, Mockery::type('string'), $this->view)
            ->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(ListRolesDQL::class))
            ->andReturn(Result::success($this->view));

        $useCase = new ListRolesUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeFalse();
    });

    it('never caches a failed query', function () {
        $broken = anError(500, 'connection lost');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new ListRolesUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before reading the cache', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('get');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListRolesUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute(new ListRolesQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
