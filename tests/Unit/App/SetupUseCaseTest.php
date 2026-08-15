<?php

declare(strict_types=1);

use App\Commands\Permission\RegisterPermissionCommand;
use App\Commands\SetupCommand;
use App\Services\Interno\RegisterPermissionUseCase;
use App\Services\Interno\SetupUseCase;
use Domain\TableModules\Interno\PermissionTM;
use Infra\Repository\IRoleRepository;
use Infra\Repository\IUserRepository;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;
use Tests\Doubles\InMemoryPermissionRepository;

/**
 * The bootstrap, and the only endpoint that runs before anyone can authenticate
 * — so its guard is not a permission but the state of the database.
 *
 * The administrator role is built from the registry rather than from a literal
 * list, which is what makes a permission introduced by a future use case granted
 * here without anyone remembering to come back. That is asserted over a real
 * catalogue, filled the way `WorkerStart` fills it, because a hand-written list
 * would test nothing but itself.
 *
 * The `hasAny` check is what stops this from being a second, unauthenticated way
 * to mint an administrator.
 */
describe('SetupUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->userTM = domain()->userTM();
        $this->roleTM = domain()->roleTM();

        // The catalogue as a worker leaves it: declared by constructors, not by
        // a list anyone maintains.
        $this->catalogue = new InMemoryPermissionRepository();
        $registrar = new RegisterPermissionUseCase(new PermissionTM(), $this->catalogue);
        $registrar->execute(new RegisterPermissionCommand('product:read'));
        $registrar->execute(new RegisterPermissionCommand('container:seal'));

        $this->command = new SetupCommand('Allan', 'allan@example.com', 'Str0ngPassword');
    });

    it('commits an administrator granting every registered permission', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('hasAny')->once()->andReturn(Result::success(false));
        $users->shouldReceive('insert')->once()->andReturn(Result::void());
        $users->shouldReceive('syncRoles')->once()->andReturn(Result::void());

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('insert')->once()
            ->with(Mockery::on(fn ($role) => $role->permissions === ['product:read', 'container:seal']))
            ->andReturn(Result::void());

        $useCase = new SetupUseCase(commitsOnce(), $users, $roles, $this->catalogue,
            $this->userTM, $this->roleTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->email)->toBe('allan@example.com')
            ->and($result->getValue()->roles[0]->permissions)
            ->toBe(['product:read', 'container:seal']);
    });

    it('refuses with 409 once the system has a user', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('hasAny')->once()->andReturn(Result::success(true));
        // The only thing standing between an anonymous caller and an
        // administrator account.
        $users->shouldNotReceive('insert');

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('insert');

        $useCase = new SetupUseCase(rollsBackOnce(), $users, $roles, $this->catalogue,
            $this->userTM, $this->roleTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(409);
    });

    it('rolls back rather than guessing when the check itself fails', function () {
        $broken = anError(500, 'connection lost');

        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('hasAny')->once()->andReturn(Result::failure($broken));
        // Reading an unreadable database as "empty" would hand out an
        // administrator on a transient outage.
        $users->shouldNotReceive('insert');

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldNotReceive('insert');

        $useCase = new SetupUseCase(rollsBackOnce(), $users, $roles, $this->catalogue,
            $this->userTM, $this->roleTM);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($broken);
    });

    it('rolls back with 422 when the first user is invalid, after the role was written', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('hasAny')->once()->andReturn(Result::success(false));
        $users->shouldNotReceive('insert');

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('insert')->once()->andReturn(Result::void());

        $useCase = new SetupUseCase(rollsBackOnce(), $users, $roles, $this->catalogue,
            $this->userTM, $this->roleTM);

        // The role row is already in the transaction: without the rollback the
        // system would come up holding an administrator role nobody has.
        $result = $useCase->execute(new SetupCommand('', 'not-an-email', 'weak'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back when the role assignment fails', function () {
        $users = Mockery::mock(IUserRepository::class);
        $users->shouldReceive('hasAny')->once()->andReturn(Result::success(false));
        $users->shouldReceive('insert')->once()->andReturn(Result::void());
        $users->shouldReceive('syncRoles')->once()->andReturn(Result::failure(anError()));

        $roles = Mockery::mock(IRoleRepository::class);
        $roles->shouldReceive('insert')->once()->andReturn(Result::void());

        $useCase = new SetupUseCase(rollsBackOnce(), $users, $roles, $this->catalogue,
            $this->userTM, $this->roleTM);

        // Committing here would leave the one account that exists holding
        // nothing, and setup already refuses to run a second time.
        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });
})->group('App', 'UseCase');
