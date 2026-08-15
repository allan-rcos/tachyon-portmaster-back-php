<?php

declare(strict_types=1);

use App\Commands\User\UpdateUserCommand;
use App\Services\Interno\UpdateUserUseCase;
use Domain\Models\Internal\User;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The update spine over the real user rules.
 *
 * The command carries a name and an e-mail and nothing else, and the table
 * module is handed the loaded user rather than a bare id — so the hash and the
 * roles come from what was stored. That is asserted on the happy path, because
 * an update that rebuilt the user from the command alone would blank the
 * password on every profile edit.
 */
describe('UpdateUserUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->registrar = registrar();
        $this->existing = new User('U1', 'Allan', 'allan@example.com', 'stored-hash', []);
        $this->command = new UpdateUserCommand(
            caller('user:update'), 'U1', 'Allan Costa', 'allan.costa@example.com',
        );
    });

    it('commits, keeping the stored password hash', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->with('U1')
            ->andReturn(Result::success($this->existing));
        $users->shouldReceive('update')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::User)
            ->andReturn(Result::void());

        $useCase = new UpdateUserUseCase(commitsOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->name)->toBe('Allan Costa')
            ->and($result->getValue()->passwordHash)->toBe('stored-hash');
    });

    it('rolls back and never updates when the user does not exist', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $users->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back when the table module rejects the new e-mail', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute(new UpdateUserCommand(
            caller('user:update'), 'U1', '', 'not-an-email',
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back and never invalidates when the write fails', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldNotReceive('findById');
        $users->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateUserUseCase(untouchedUnitOfWork(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute(new UpdateUserCommand(
            stranger(), 'U1', 'Allan Costa', 'allan.costa@example.com',
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
