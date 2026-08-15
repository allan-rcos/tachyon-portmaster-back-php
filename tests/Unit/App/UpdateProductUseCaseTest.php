<?php

declare(strict_types=1);

use App\Commands\Product\UpdateProductCommand;
use App\Services\Interno\UpdateProductUseCase;
use Domain\Enums\RiskClass;
use Domain\Models\Internal\Product;
use Infra\Repository\IProductRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * An update reads before it writes, which gives it one failure point more than a
 * create: the row may not be there. Each of the four — the load, the rules, the
 * write, and the guard — has to roll back and leave the cache alone.
 */
describe('UpdateProductUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->productTM = domain()->productTM();
        $this->registrar = registrar();
        $this->existing = new Product('PROD1', 'Diesel', 0.85, RiskClass::Class3FlammableLiquids);

        $this->command = new UpdateProductCommand(
            context: caller('product:update'),
            id: 'PROD1',
            name: 'Diesel S10',
            density: 0.84,
            riskClass: RiskClass::Class3FlammableLiquids,
        );
    });

    it('commits and answers the rebuilt product', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->with('PROD1')
            ->andReturn(Result::success($this->existing));
        $products->shouldReceive('update')->once()->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Product)
            ->andReturn(Result::void());

        $useCase = new UpdateProductUseCase(commitsOnce(), $views, $products, $this->productTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->id)->toBe('PROD1')
            ->and($result->getValue()->name)->toBe('Diesel S10');
    });

    it('rolls back and never updates when the product does not exist', function () {
        $missing = anError(404, 'no such product');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $products->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateProductUseCase(rollsBackOnce(), $views, $products, $this->productTM,
            $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back when the table module rejects the new values', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $products->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateProductUseCase(rollsBackOnce(), $views, $products, $this->productTM,
            $this->registrar);

        $result = $useCase->execute(new UpdateProductCommand(
            context: caller('product:update'),
            id: 'PROD1',
            name: '',
            density: -1.0,
            riskClass: RiskClass::None,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(422);
    });

    it('rolls back and never invalidates when the write fails', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $products->shouldReceive('update')->once()->andReturn(Result::failure(anError()));

        // Nothing was committed, so nothing may be dropped from the cache.
        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateProductUseCase(rollsBackOnce(), $views, $products, $this->productTM,
            $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldNotReceive('findById');
        $products->shouldNotReceive('update');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new UpdateProductUseCase(untouchedUnitOfWork(), $views, $products,
            $this->productTM, $this->registrar);

        $result = $useCase->execute(new UpdateProductCommand(
            context: stranger(),
            id: 'PROD1',
            name: 'Diesel S10',
            density: 0.84,
            riskClass: RiskClass::Class3FlammableLiquids,
        ));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
