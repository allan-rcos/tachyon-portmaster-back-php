<?php

declare(strict_types=1);

use App\Commands\Role\UpdateRolePermissionsCommand;
use App\Services\Interno\UpdateRolePermissionsUseCase;
use Domain\Models\Internal\Role;
use Infra\Repository\IPermissionRepository;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The most consequential write in the layer: every user holding the role is
 * affected on their next request, and a caller who can reach it can grant
 * themselves anything.
 *
 * It replaces the set rather than merging into it — an empty list is how a role
 * is stripped, and that has to work — while the id and the name survive, which
 * is asserted because a permission edit that renamed the role would be
 * invisible until someone went looking for it.
 */
describe('UpdateRolePermissionsUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->roleTM = domain()->roleTM();
        $this->registrar = registrar();
        $this->existing = new Role('R1', 'Operator', ['product:read']);
        $this->command = new UpdateRolePermissionsCommand(
            caller('role:update-permissions'), 'R1', ['product:read', 'container:read'],
        );
    });

    it('commits the replacement set, keeping id and name', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->andReturn([]);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('findById')->once()->with('R1')
            ->andReturn(Result::success($this->existing));
        $roles->shouldReceive('update')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Role)
            ->andReturn(Result::void());

        $useCase = new UpdateRolePermissionsUseCase(commitsOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->id)->toBe('R1')
            ->and($result->getValue()->name)->toBe('Operator')
            ->and($result->getValue()->permissions)->toBe(['product:read', 'container:read']);
    });

    it('commits an empty set, which is how a role is stripped', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->with([])->andReturn([]);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $roles->shouldReceive('update')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->andReturn(Result::void());

        $useCase = new UpdateRolePermissionsUseCase(commitsOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute(new UpdateRolePermissionsCommand(
            caller('role:update-permissions'), 'R1', [],
        ));

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->permissions)->toBe([]);
    });

    it('rolls back and never asks about permissions when the role is missing', function () {
        $missing = anError(404, 'no such role');

        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldNotReceive('unknown');

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $roles->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateRolePermissionsUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('refuses with 422 and names every unregistered slug', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->andReturn(['product:teleport']);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $roles->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateRolePermissionsUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute($this->command);
        $error = Leaf::getError($result->getErrorId());

        expect($result->isSuccess())->toBeFalse()
            ->and($error?->code)->toBe(422)
            ->and($error?->details?->get('unknown'))->toBe('product:teleport');
    });

    it('rolls back and never invalidates when the write fails', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->andReturn([]);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $roles->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateRolePermissionsUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldNotReceive('unknown');

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('findById');
        $roles->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateRolePermissionsUseCase(untouchedUnitOfWork(), $views, $roles,
            $this->roleTM, $permissions, $this->registrar);

        $result = $useCase->execute(new UpdateRolePermissionsCommand(stranger(), 'R1', []));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
