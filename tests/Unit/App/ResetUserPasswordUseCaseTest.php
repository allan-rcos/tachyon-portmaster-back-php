<?php

declare(strict_types=1);

use App\Commands\User\ResetUserPasswordCommand;
use App\Services\Interno\ResetUserPasswordUseCase;
use Domain\Models\Internal\User;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * An administrator setting someone else's password, which is why it asks for no
 * current one — the caller does not have it.
 *
 * That is the difference from {@see \Tests\Unit\App\ChangePasswordUseCaseTest},
 * and it is what makes the permission the only thing standing between a holder
 * and every account. The strength rules still apply, because a reset is a
 * perfectly ordinary way to end up with a weak password.
 */
describe('ResetUserPasswordUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->registrar = registrar();
        $this->existing = new User('U1', 'Allan', 'allan@example.com', 'old-hash', []);
        $this->command = new ResetUserPasswordCommand(
            caller('user:change-password'), 'U1', 'N3wStrongPassword',
        );
    });

    it('commits a re-hashed password without echoing it back', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->with('U1')
            ->andReturn(Result::success($this->existing));
        $users->shouldReceive('update')->once()
            ->with(Mockery::on(fn ($u) => $u->passwordHash !== 'old-hash'
                && $u->passwordHash !== 'N3wStrongPassword'))
            ->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::User)
            ->andReturn(Result::void());

        $useCase = new ResetUserPasswordUseCase(commitsOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        // Void, not the user: nothing about a password reset belongs in a
        // response body.
        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('rolls back and never writes when the user does not exist', function () {
        $missing = anError(404, 'no such user');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $users->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new ResetUserPasswordUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back with 422 when the new password is too weak', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $users->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new ResetUserPasswordUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute(new ResetUserPasswordCommand(
            caller('user:change-password'), 'U1', 'weak',
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

        $useCase = new ResetUserPasswordUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldNotReceive('findById');
        $users->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new ResetUserPasswordUseCase(untouchedUnitOfWork(), $views, $users,
            $this->userTM, $this->registrar);

        $result = $useCase->execute(new ResetUserPasswordCommand(
            stranger(), 'U1', 'N3wStrongPassword',
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
