<?php

declare(strict_types=1);

use App\Commands\User\CreateUserCommand;
use App\Services\Interno\CreateUserUseCase;
use Domain\Models\Internal\User;
use Infra\Repository\IUserRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * The one create in the application that reads before it writes, and the reason
 * is a status code.
 *
 * The e-mail is unique in the schema, so an insert of a duplicate would fail on
 * its own — as a driver error, which reaches the client as a 500 and reads as
 * "the server is broken" rather than "that address is taken". The lookup exists
 * to make it a 409, and the 404 from that lookup is the *expected* answer: it is
 * the only one that lets the write proceed. Any other failure is a real one and
 * must stop the write, which is the branch most easily lost in a refactor.
 */
describe('CreateUserUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->registrar = registrar();
        $this->command = new CreateUserCommand(
            context: caller('user:create'),
            name: 'Allan',
            email: 'allan@example.com',
            initialPassword: 'Str0ngPassword',
            roleIds: ['R1'],
        );
    });

    it('commits, hashing the password and syncing the roles', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(Result::failure(anError(404, 'none')));
        $users->shouldReceive('insert')->once()->andReturn(Result::void());
        $users->shouldReceive('syncRoles')->once()->with(Mockery::type('string'), ['R1'])
            ->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::User)
            ->andReturn(Result::void());

        $useCase = new CreateUserUseCase(commitsOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->email)->toBe('allan@example.com')
            // The plaintext must not have survived the table module.
            ->and($result->getValue()->passwordHash)->not->toBe('Str0ngPassword');
    });

    it('refuses a duplicate e-mail with 409 rather than letting the insert fail', function () {
        $taken = new User('U2', 'Someone', 'allan@example.com', 'hash', []);

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(Result::success($taken));
        $users->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('stops on a lookup failure that is not a 404', function () {
        $broken = anError(500, 'connection lost');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(Result::failure($broken));
        // Treating this as "no such user" would insert a duplicate the moment
        // the database came back.
        $users->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('rolls back when the table module rejects the input, without inserting', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(Result::failure(anError(404, 'none')));
        $users->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute(new CreateUserCommand(
            context: caller('user:create'),
            name: '',
            email: 'not-an-email',
            initialPassword: 'weak',
            roleIds: [],
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back when the role sync fails, after the user was inserted', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('findByEmail')->once()->andReturn(Result::failure(anError(404, 'none')));
        $users->shouldReceive('insert')->once()->andReturn(Result::void());
        $users->shouldReceive('syncRoles')->once()->andReturn(Result::failure(anError()));

        // The user row is already written at this point: without the rollback
        // the account would exist with no roles and no way to reach anything.
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateUserUseCase(rollsBackOnce(), $views, $users, $this->userTM,
            $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldNotReceive('findByEmail');
        $users->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateUserUseCase(untouchedUnitOfWork(), $views, $users, $this->userTM,
            $this->registrar);

        $result = $useCase->execute(new CreateUserCommand(
            context: stranger(),
            name: 'Allan',
            email: 'allan@example.com',
            initialPassword: 'Str0ngPassword',
            roleIds: [],
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
