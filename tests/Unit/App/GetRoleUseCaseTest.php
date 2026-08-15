<?php

declare(strict_types=1);

use App\Queries\Role\GetRoleQuery;
use App\Services\Interno\GetRoleUseCase;
use Infra\Query\Interno\GetRoleDQL;
use Infra\Query\IQueryRepository;
use Infra\Query\Role\RoleViewItem;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The read-by-id shape, behind `role:list` rather than a permission of its own —
 * reading one role and reading the page of them are the same privilege.
 */
describe('GetRoleUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->query = new GetRoleQuery(caller('role:list'), 'R1');
    });

    it('answers the view item the query found', function () {
        $item = new RoleViewItem('R1', 'Operator', 3, ['product:read']);

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->with(Mockery::type(GetRoleDQL::class))
            ->andReturn(Result::success($item));

        $useCase = new GetRoleUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBe($item);
    });

    it('turns an empty result into a 404', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::void());

        $useCase = new GetRoleUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(404);
    });

    it('passes a query failure through as it is', function () {
        $broken = anError(500, 'connection lost');

        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldReceive('run')->once()->andReturn(Result::failure($broken));

        $useCase = new GetRoleUseCase($queries, $this->registrar);

        $result = $useCase->execute($this->query);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('refuses a caller without the permission before querying', function () {
        $queries = Mockery::mock(IQueryRepository::class);
        $queries->shouldNotReceive('run');

        $useCase = new GetRoleUseCase($queries, $this->registrar);

        $result = $useCase->execute(new GetRoleQuery(stranger(), 'R1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
