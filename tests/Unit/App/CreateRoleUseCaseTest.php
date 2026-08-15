<?php

declare(strict_types=1);

use App\Commands\Role\CreateRoleCommand;
use App\Services\Interno\CreateRoleUseCase;
use Infra\Repository\IPermissionRepository;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The create spine, plus the one check the table module cannot make.
 *
 * Whether a slug is registered is a question about state — the catalogue is
 * filled at `WorkerStart` by the use case constructors — and the domain layer
 * neither reaches it nor should. So the use case asks the repository, and the
 * ordering is asserted: the check runs *before* the table module, so a payload
 * that is wrong in both ways is refused for the reason a client can act on.
 *
 * Every offending slug is named in the 422, which is what lets a client fix its
 * payload in one round trip instead of discovering them one at a time.
 */
describe('CreateRoleUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->roleTM = domain()->roleTM();
        $this->registrar = registrar();
        $this->command = new CreateRoleCommand(
            caller('role:create'), 'Operator', ['product:read', 'container:read'],
        );
    });

    it('commits a role granting only registered permissions', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()
            ->with(['product:read', 'container:read'])->andReturn([]);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('insert')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Role)
            ->andReturn(Result::void());

        $useCase = new CreateRoleUseCase(commitsOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->name)->toBe('Operator')
            ->and($result->getValue()->permissions)->toBe(['product:read', 'container:read']);
    });

    it('refuses with 422 and names every unregistered slug', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()
            ->andReturn(['product:teleport', 'container:levitate']);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateRoleUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute($this->command);
        $error = Leaf::getError($result->getErrorId());

        expect($result->isSuccess())->toBeFalse()
            ->and($error?->code)->toBe(422)
            // Both, in one answer — not the first one found.
            ->and($error?->details?->get('unknown'))
            ->toBe('product:teleport, container:levitate');
    });

    it('checks the permissions before the table module runs', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->andReturn(['product:teleport']);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateRoleUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        // The name is blank too, which the table module would refuse. The
        // unknown slug is the answer, so the check ran first.
        $result = $useCase->execute(new CreateRoleCommand(
            caller('role:create'), '', ['product:teleport'],
        ));

        expect(Leaf::getError($result->getErrorId())?->details?->hasKey('unknown'))->toBeTrue();
    });

    it('rolls back when the table module rejects the name', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->andReturn([]);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateRoleUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute(new CreateRoleCommand(caller('role:create'), '', []));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back and never invalidates when the insert fails', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldReceive('unknown')->once()->andReturn([]);

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('insert')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateRoleUseCase(rollsBackOnce(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $permissions = Mockery::mock(IPermissionRepository::class);
        $permissions->shouldNotReceive('unknown');

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateRoleUseCase(untouchedUnitOfWork(), $views, $roles, $this->roleTM,
            $permissions, $this->registrar);

        $result = $useCase->execute(new CreateRoleCommand(stranger(), 'Operator', []));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
