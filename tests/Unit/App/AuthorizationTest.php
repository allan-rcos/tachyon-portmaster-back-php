<?php

declare(strict_types=1);

use App\Commands\Product\CreateProductCommand;
use App\Context\RoleContext;
use App\Context\UserContext;
use App\Security\AuthorizesWithPermission;
use App\Services\Interno\CreateProductUseCase;
use App\Services\Interno\RegisterPermissionUseCase;
use App\Services\IRegisterPermissionUseCase;
use Domain\Enums\RiskClass;
use Domain\TableModules\Interno\PermissionTM;
use Infra\Database\IUnitOfWork;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\IProductRepository;
use Shared\Exceptions\Leaf;
use Tests\Doubles\InMemoryPermissionRepository;

/**
 * The core of the refactor: authorization is decided by the use case, not the
 * API layer. These cases pin that a caller lacking the permission is stopped
 * *before* the use case touches the unit of work at all.
 */
describe('use case authorization', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $this->registry = new InMemoryPermissionRepository();
        $this->registrar = new RegisterPermissionUseCase(new PermissionTM(), $this->registry);
    });

    $contextWith = static fn (string ...$permissions): UserContext => new UserContext(
        id: 'U1',
        name: 'Allan',
        email: 'a@example.com',
        roles: [new RoleContext('R1', 'Papel', $permissions)],
    );

    it('registers its own permission when constructed', function () {
        new CreateProductUseCase(
            Mockery::mock(IUnitOfWork::class),
            Mockery::mock(IViewCacheRepository::class),
            Mockery::mock(IProductRepository::class),
            Mockery::mock(Domain\TableModules\IProductTM::class),
            $this->registrar,
        );

        expect($this->registry->has('product:create'))->toBeTrue()
            // 0 is reserved for "built but not registered", so a registered
            // permission must carry a real index.
            ->and($this->registry->getBySlug('product:create')?->id)->toBe(1);
    });

    it('denies with 403 and never opens a transaction', function () use ($contextWith) {
        // A strict mock: any call to the unit of work fails the test, proving the
        // guard runs first rather than merely returning a failure at the end.
        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldNotReceive('begin');

        $useCase = new CreateProductUseCase(
            $unitOfWork,
            // Strict too: a denied caller must not reach the cache either.
            Mockery::mock(IViewCacheRepository::class),
            Mockery::mock(IProductRepository::class),
            Mockery::mock(Domain\TableModules\IProductTM::class),
            $this->registrar,
        );

        $result = $useCase->execute(new CreateProductCommand(
            context: $contextWith('product:read'),
            name: 'Diesel',
            density: 0.85,
            riskClass: RiskClass::Class3FlammableLiquids,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(403);
    });

    it('lets the holder through to the transaction', function () use ($contextWith) {
        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        // Reaching begin() is the assertion: the guard let the caller past.
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Shared\Exceptions\Result::void());
        // Every stub needs an explicit return: Result is readonly, so Mockery
        // cannot auto-generate one for an unstubbed call.
        $unitOfWork->shouldReceive('rollback')->zeroOrMoreTimes()->andReturn(Shared\Exceptions\Result::void());

        // The table module answers with a failure so the use case stops there:
        // reaching it at all is what proves the permission guard let us past.
        $productTM = Mockery::mock(Domain\TableModules\IProductTM::class);
        $productTM->shouldReceive('create')->once()->andReturn(
            Shared\Exceptions\Result::failure(
                Leaf::newError(new Shared\Exceptions\LeafContext('validation stub', code: 422)),
            ),
        );

        $useCase = new CreateProductUseCase(
            $unitOfWork,
            // The write never lands, so nothing may be invalidated.
            Mockery::mock(IViewCacheRepository::class),
            Mockery::mock(IProductRepository::class),
            $productTM,
            $this->registrar,
        );

        $result = $useCase->execute(new CreateProductCommand(
            context: $contextWith('product:create'),
            name: 'Diesel',
            density: 0.85,
            riskClass: RiskClass::Class3FlammableLiquids,
        ));

        // 422 from the table module, not 403: the caller was authorized.
        expect(Leaf::getError($result->getErrorId())?->code)->toBe(422);
    });

    it('grants a permission held through any one of several roles', function () {
        $context = new UserContext('U1', 'Allan', 'a@example.com', [
            new RoleContext('R1', 'Leitor', ['product:read']),
            new RoleContext('R2', 'Operador', ['container:seal']),
        ]);

        expect($context->hasPermission('container:seal'))->toBeTrue()
            ->and($context->hasPermission('product:read'))->toBeTrue()
            ->and($context->hasPermission('product:delete'))->toBeFalse();
    });

    it('de-duplicates the effective permission union across roles', function () {
        $context = new UserContext('U1', 'Allan', 'a@example.com', [
            new RoleContext('R1', 'A', ['product:read', 'metrics:read']),
            new RoleContext('R2', 'B', ['product:read']),
        ]);

        expect($context->permissions())->toBe(['product:read', 'metrics:read']);
    });

    it('refuses to boot with a malformed permission slug', function () {
        $useCase = new class($this->registrar) {
            use AuthorizesWithPermission;

            public function __construct(IRegisterPermissionUseCase $registrar)
            {
                $this->permission = $this->declarePermission($registrar, 'NOT A SLUG');
            }
        };
    })->throws(RuntimeException::class, 'Cannot register permission "NOT A SLUG"');
})->group('App', 'UseCase');
