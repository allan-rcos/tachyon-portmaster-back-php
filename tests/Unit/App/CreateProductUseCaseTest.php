<?php

declare(strict_types=1);

use App\Commands\Product\CreateProductCommand;
use App\Context\RoleContext;
use App\Context\UserContext;
use App\Services\Interno\CreateProductUseCase;
use App\Services\Interno\RegisterPermissionUseCase;
use Domain\Enums\RiskClass;
use Domain\ID\IDatabaseIdGenerator;
use Domain\TableModules\Interno\PermissionTM;
use Domain\TableModules\Interno\ProductTM;
use Infra\Database\IUnitOfWork;
use Tests\Doubles\InMemoryPermissionRepository;
use Infra\Repository\IProductRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\LeafContext;
use Shared\Exceptions\Result;

/**
 * Beyond the 403 guard (see AuthorizationTest), these pin the transactional
 * spine of a write use case: commit on success, rollback on any repository
 * failure, and never both.
 *
 * They also pin where the cache invalidation sits relative to that spine. It
 * belongs *after* a successful commit and nowhere else: before it, a concurrent
 * read would repopulate the cache from the state being replaced, and on a
 * rolled-back path there is nothing to invalidate because nothing changed. Both
 * halves are asserted, because only asserting the happy path would let the
 * invalidation drift up above the commit without any test noticing.
 */
describe('CreateProductUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $registrar = new RegisterPermissionUseCase(new PermissionTM(), new InMemoryPermissionRepository());
        $ids = Mockery::mock(IDatabaseIdGenerator::class);
        $ids->shouldReceive('generate')->andReturn('PROD1');
        $this->productTM = new ProductTM($ids);
        $this->registrar = $registrar;

        $this->context = new UserContext('U1', 'Allan', 'a@example.com', [
            new RoleContext('R1', 'Maker', ['product:create']),
        ]);

        $this->command = new CreateProductCommand(
            context: $this->context,
            name: 'Diesel',
            density: 0.85,
            riskClass: RiskClass::Class3FlammableLiquids,
        );
    });

    it('commits and returns the built product on the happy path', function () {
        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
        $unitOfWork->shouldReceive('commit')->once()->andReturn(Result::void());
        $unitOfWork->shouldNotReceive('rollback');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('insert')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Product)
            ->andReturn(Result::void());

        $useCase = new CreateProductUseCase($unitOfWork, $views, $products, $this->productTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->id)->toBe('PROD1')
            ->and($result->getValue()->name)->toBe('Diesel');
    });

    it('rolls back and never commits when the insert fails', function () {
        $insertError = Leaf::newError(new LeafContext('db down', code: 500));

        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
        $unitOfWork->shouldReceive('rollback')->once()->andReturn(Result::void());
        $unitOfWork->shouldNotReceive('commit');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('insert')->once()->andReturn(Result::failure($insertError));

        // Nothing was committed, so nothing may be dropped from the cache.
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateProductUseCase($unitOfWork, $views, $products, $this->productTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($insertError);
    });

    it('rolls back when the table module rejects the input, without inserting', function () {
        $unitOfWork = Mockery::mock(IUnitOfWork::class);
        $unitOfWork->shouldReceive('begin')->once()->andReturn(Result::void());
        $unitOfWork->shouldReceive('rollback')->once()->andReturn(Result::void());
        $unitOfWork->shouldNotReceive('commit');

        // Insert must never be reached: validation fails first.
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldNotReceive('insert');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new CreateProductUseCase($unitOfWork, $views, $products, $this->productTM,
            $this->registrar);

        $result = $useCase->execute(new CreateProductCommand(
            context: $this->context,
            name: '',
            density: -1.0,
            riskClass: RiskClass::None,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->code)->toBe(422);
    });
})->group('App', 'UseCase');
