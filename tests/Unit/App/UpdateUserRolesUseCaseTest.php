<?php

declare(strict_types=1);

use App\Commands\User\UpdateUserRolesCommand;
use App\Services\Interno\UpdateUserRolesUseCase;
use Domain\Models\Internal\User;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The write that decides what someone can do, and it has no table module at all
 * — a role assignment is a relation, not a shape, so there is nothing to
 * validate about the list itself.
 *
 * What it must not skip is the load: `syncRoles` on an id that does not exist
 * would write pivot rows for nobody and answer 200. The empty list is a
 * deliberate case rather than an edge one, because it is how access is revoked.
 */
describe('UpdateUserRolesUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->existing = new User('U1', 'Allan', 'allan@example.com', 'hash', []);
        $this->command = new UpdateUserRolesCommand(
            caller('user:update-roles'), 'U1', ['R1', 'R2'],
        );
    });

    it('commits the replacement set', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->with('U1')
            ->andReturn(Result::success($this->existing));
        $users->shouldReceive('syncRoles')->once()->with('U1', ['R1', 'R2'])
            ->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::User)
            ->andReturn(Result::void());

        $useCase = new UpdateUserRolesUseCase(commitsOnce(), $views, $users, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->id)->toBe('U1');
    });

    it('commits an empty set, which is how access is revoked', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('syncRoles')->once()->with('U1', [])->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->andReturn(Result::void());

        $useCase = new UpdateUserRolesUseCase(commitsOnce(), $views, $users, $this->registrar);

        $result = $useCase->execute(new UpdateUserRolesCommand(
            caller('user:update-roles'), 'U1', [],
        ));

        expect($result->isSuccess())->toBeTrue();
    });

    it('rolls back and never syncs when the user does not exist', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        // Without this the pivot would gain rows for a user that is not there.
        $users->shouldNotReceive('syncRoles');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserRolesUseCase(rollsBackOnce(), $views, $users, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back and never invalidates when the sync fails', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('syncRoles')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserRolesUseCase(rollsBackOnce(), $views, $users, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldNotReceive('findById');
        $users->shouldNotReceive('syncRoles');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserRolesUseCase(untouchedUnitOfWork(), $views, $users,
            $this->registrar);

        $result = $useCase->execute(new UpdateUserRolesCommand(stranger(), 'U1', ['R1']));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
