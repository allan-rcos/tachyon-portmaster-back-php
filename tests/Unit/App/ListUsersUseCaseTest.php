<?php

declare(strict_types=1);

use App\Events\MetaEvent;
use App\Queries\User\ListUsersQuery;
use App\Services\Interno\ListUsersUseCase;
use Infra\Query\Interno\ListUsersDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\User\UserListView;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The cached-listing shape, over the one listing that paginates by page number
 * rather than by cursor.
 *
 * The page is part of the cache key for the same reason the cursor is elsewhere:
 * without it, page 2 would be answered with page 1 for the whole TTL.
 */
describe('ListUsersUseCase caching', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->events = events();
        $this->query = new ListUsersQuery(caller('user:list'), page: 1, limit: 10);
        $this->view = new UserListView([]);
    });

    it('answers from the cache without querying, and reports the hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::success($this->view));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListUsersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($this->view)
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeTrue();
    });

    it('queries and stores on a miss, and reports no hit', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldReceive('put')->once()
            ->with(ViewCacheGroup::User, Mockery::type('string'), $this->view)
            ->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(ListUsersDQL::class))
            ->andReturn(Result::success($this->view));

        $useCase = new ListUsersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($this->events->captured(MetaEvent::ViewCacheHit))->toBeFalse();
    });

    it('keys the cache by the page', function () {
        $keys = [];

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->twice()
            ->andReturnUsing(function (ViewCacheGroup $group, string $key) use (&$keys) {
                $keys[] = $key;

                return Result::failure(anError(404, 'miss'));
            });
        $views->shouldReceive('put')->twice()->andReturn(Result::void());

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->twice()->andReturn(Result::success($this->view));

        $useCase = new ListUsersUseCase($queries, $views, $this->events, $this->registrar);

        $useCase->execute(new ListUsersQuery(caller('user:list'), page: 1));
        $useCase->execute(new ListUsersQuery(caller('user:list'), page: 2));

        expect($keys[0])->not->toBe($keys[1]);
    });

    it('never caches a failed query', function () {
        $broken = anError(500, 'connection lost');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('get')->once()->andReturn(Result::failure(anError(404, 'miss')));
        $views->shouldNotReceive('put');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new ListUsersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before reading the cache', function () {
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('get');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new ListUsersUseCase($queries, $views, $this->events, $this->registrar);

        $result = $useCase->execute(new ListUsersQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
