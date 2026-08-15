<?php

declare(strict_types=1);

use App\Queries\Role\ListPermissionsQuery;
use App\Services\Interno\ListPermissionsUseCase;
use Domain\Models\Internal\Permission;
use Ds\Seq;
use Infra\Repository\IPermissionRepository;
use Shared\Exceptions\Leaf;

/**
 * The only listing with no query, no cursor and no cache: the catalogue is
 * already in memory, filled at `WorkerStart` by the use case constructors
 * themselves.
 *
 * So the search runs in PHP, over {@see \Infra\Text\SearchKey} — the same
 * normalisation the `search_*` columns use elsewhere — rather than as a `LIKE`.
 * That is what makes an accent-insensitive match here behave the way it does on
 * every other endpoint.
 */
describe('ListPermissionsUseCase', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->catalogue = new Seq([
            new Permission('product:read', 1),
            new Permission('product:create', 2),
            new Permission('container:seal', 3),
        ]);
    });

    it('answers the whole catalogue when nothing is searched for', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('all')->once()->andReturn($this->catalogue);

        $useCase = new ListPermissionsUseCase($permissions, $this->registrar);

        $result = $useCase->execute(new ListPermissionsQuery(caller('permission:list')));

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->count())->toBe(3);
    });

    it('filters by substring, not by prefix', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('all')->once()->andReturn($this->catalogue);

        $useCase = new ListPermissionsUseCase($permissions, $this->registrar);

        $result = $useCase->execute(new ListPermissionsQuery(
            caller('permission:list'), search: 'read',
        ));

        // `read` is the action half of the slug: a prefix match would find
        // nothing, which is the mistake this asserts against.
        expect($result->getValue()->count())->toBe(1)
            ->and($result->getValue()->first()->slug)->toBe('product:read');
    });

    it('treats a blank search as no search at all', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('all')->once()->andReturn($this->catalogue);

        $useCase = new ListPermissionsUseCase($permissions, $this->registrar);

        $result = $useCase->execute(new ListPermissionsQuery(
            caller('permission:list'), search: '   ',
        ));

        expect($result->getValue()->count())->toBe(3);
    });

    it('answers an empty list rather than a 404 when nothing matches', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('all')->once()->andReturn($this->catalogue);

        $useCase = new ListPermissionsUseCase($permissions, $this->registrar);

        $result = $useCase->execute(new ListPermissionsQuery(
            caller('permission:list'), search: 'teleport',
        ));

        // A listing that matched nothing is still a listing.
        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->isEmpty())->toBeTrue();
    });

    it('refuses a caller without the permission before reading the catalogue', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldNotReceive('all');

        $useCase = new ListPermissionsUseCase($permissions, $this->registrar);

        $result = $useCase->execute(new ListPermissionsQuery(stranger()));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
