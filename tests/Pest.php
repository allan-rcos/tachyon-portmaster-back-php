<?php

declare(strict_types=1);

use App\Context\RoleContext;
use App\Context\UserContext;
use App\Events\IMetaEventStack;
use App\Services\Interno\RegisterPermissionUseCase;
use App\Services\IRegisterPermissionUseCase;
use Domain\Config\DomainConfig;
use Domain\DomainRegister;
use Domain\IDomainProvider;
use Domain\TableModules\Interno\PermissionTM;
use Infra\Database\IUnitOfWork;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;
use Tests\Doubles\InMemoryMetaEventStack;
use Tests\Doubles\InMemoryPermissionRepository;

/**
 * The real domain layer, built exactly as a worker builds it.
 *
 * Use case tests mock **infrastructure only** — repositories, the unit of work,
 * the query runner. Everything from the domain is the real thing, because a use
 * case test that mocks its table module proves that the mock agrees with the
 * test rather than that the use case is wired to the rules. When a table module
 * refuses input here, it refuses for the reason it would in production.
 *
 * Memoized per process: it is stateless, and the id generators are the only
 * thing in it that carries any, which is a counter that nothing asserts on.
 */
function domain(): IDomainProvider
{
    static $provider = null;

    return $provider ??= DomainRegister::execute(new DomainConfig(), serverId: 1);
}

/**
 * A real permission registrar over the in-memory catalogue.
 *
 * Not a mock, because a guarded use case calls this from its own constructor:
 * a mock would have to be taught the whole handshake before the object under
 * test could even be built. Fresh per call, so one test's registrations never
 * leak into another's.
 */
function registrar(): IRegisterPermissionUseCase
{
    return new RegisterPermissionUseCase(new PermissionTM(), new InMemoryPermissionRepository());
}

/**
 * A caller holding exactly these permission slugs.
 *
 * @param  string  ...$permissions  Slugs the single role grants.
 */
function caller(string ...$permissions): UserContext
{
    return new UserContext('U1', 'Allan', 'a@example.com', [
        new RoleContext('R1', 'Tester', $permissions),
    ]);
}

/**
 * A caller holding no permission at all, for the 403 guard.
 */
function stranger(): UserContext
{
    return new UserContext('U9', 'Nobody', 'n@example.com', [
        new RoleContext('R9', 'Guest', []),
    ]);
}

/**
 * A unit of work that expects to begin and commit, and must never roll back.
 */
function commitsOnce(): IUnitOfWork
{
    $unitOfWork = Mockery::mock(IUnitOfWork::class);
    $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
    $unitOfWork->shouldReceive('commit')->once()->andReturn(Result::void());
    $unitOfWork->shouldNotReceive('rollback');

    return $unitOfWork;
}

/**
 * A unit of work that expects to begin and roll back, and must never commit.
 *
 * The assertion that matters most in this suite. `Result` is returned rather
 * than thrown precisely so a rollback cannot be skipped by an exception
 * unwinding past it, and this is what proves it was not skipped.
 */
function rollsBackOnce(): IUnitOfWork
{
    $unitOfWork = Mockery::mock(IUnitOfWork::class);
    $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
    $unitOfWork->shouldReceive('rollback')->once()->andReturn(Result::void());
    $unitOfWork->shouldNotReceive('commit');

    return $unitOfWork;
}

/**
 * A unit of work no guarded use case may touch, for the 403 guard.
 *
 * Strict on purpose: any call at all fails the test, which is what proves the
 * guard runs *before* any work rather than merely returning a failure at the
 * end.
 */
function untouchedUnitOfWork(): IUnitOfWork
{
    $unitOfWork = Mockery::mock(IUnitOfWork::class);
    $unitOfWork->shouldNotReceive('begin');
    $unitOfWork->shouldNotReceive('commit');
    $unitOfWork->shouldNotReceive('rollback');

    return $unitOfWork;
}

/**
 * The meta event stack, faked into a plain array.
 *
 * See {@see InMemoryMetaEventStack} for why the production one cannot be used
 * here: it stores events in the coroutine context, and a Pest test has no
 * coroutine, so the real one is silently inert.
 */
function events(): IMetaEventStack
{
    return new InMemoryMetaEventStack();
}

/**
 * A registered failure id, for standing in as a repository's error.
 *
 * @param  int  $code  The status the failure carries.
 */
function anError(int $code = 500, string $message = 'infrastructure is down'): int
{
    return Leaf::newError(new LeafContext($message, code: $code));
}

/**
 * The status code a failed Result carries.
 */
function codeOf(Result $result): ?int
{
    return Leaf::getError($result->getErrorId())?->code;
}
