<?php

declare(strict_types=1);

use App\Commands\User\DeleteUserCommand;
use App\Services\Interno\DeleteUserUseCase;
use Domain\Models\Internal\User;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * A delete with an extra step: the role assignments go first.
 *
 * The order is the whole point. Deleting the user row before clearing the pivot
 * would leave assignments pointing at nobody, and the id is generated rather
 * than serial, so a later user could not inherit them — but the rows would still
 * be counted by every query that joins through it. Both the ordering and the
 * rollback when the sync fails are asserted, because the failure lands *after*
 * the pivot is already emptied.
 */
describe('DeleteUserUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->existing = new User('U1', 'Allan', 'allan@example.com', 'hash', []);
        $this->command = new DeleteUserCommand(caller('user:delete'), 'U1');
    });

    it('clears the roles before deleting, and commits', function () {
        $order = [];

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('syncRoles')->once()->with('U1', [])
            ->andReturnUsing(function () use (&$order) {
                $order[] = 'syncRoles';

                return Result::void();
            });
        $users->shouldReceive('delete')->once()->with('U1')
            ->andReturnUsing(function () use (&$order) {
                $order[] = 'delete';

                return Result::void();
            });

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::User)
            ->andReturn(Result::void());

        $useCase = new DeleteUserUseCase(commitsOnce(), $views, $users, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull()
            ->and($order)->toBe(['syncRoles', 'delete']);
    });

    it('surfaces the 404 and never deletes when the user is not there', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $users->shouldNotReceive('syncRoles');
        $users->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteUserUseCase(rollsBackOnce(), $views, $users, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back when the role sync fails, without deleting the user', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('syncRoles')->once()->andReturn(Result::failure(anError()));
        $users->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteUserUseCase(rollsBackOnce(), $views, $users, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('rolls back when the delete fails, restoring the cleared roles', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('syncRoles')->once()->andReturn(Result::void());
        $users->shouldReceive('delete')->once()->andReturn(Result::failure(anError()));

        // The pivot is already empty at this point: without the rollback the
        // user would survive the request having silently lost every role.
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteUserUseCase(rollsBackOnce(), $views, $users, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldNotReceive('findById');
        $users->shouldNotReceive('syncRoles');
        $users->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteUserUseCase(untouchedUnitOfWork(), $views, $users, $this->registrar);

        $result = $useCase->execute(new DeleteUserCommand(stranger(), 'U1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
