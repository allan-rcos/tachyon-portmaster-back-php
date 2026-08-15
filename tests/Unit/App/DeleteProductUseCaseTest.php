<?php

declare(strict_types=1);

use App\Commands\Product\DeleteProductCommand;
use App\Services\Interno\DeleteProductUseCase;
use Domain\Enums\RiskClass;
use Domain\Models\Internal\Product;
use Infra\Repository\IProductRepository;
use Infra\Repository\IViewCacheRepository;
use Infra\Repository\ViewCacheGroup;
use Shared\Exceptions\Leaf;
use Shared\Exceptions\Result;

/**
 * A delete has no table module — there is nothing to validate — so its whole
 * shape is the transaction and the existence check.
 *
 * The load before the delete is not redundant: the repository's delete is a
 * no-op on a row that is not there, so without the load a request to delete
 * something that never existed would answer 204 and lie about it.
 */
describe('DeleteProductUseCase transaction', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();

        $this->registrar = registrar();
        $this->existing = new Product('PROD1', 'Diesel', 0.85, RiskClass::Class3FlammableLiquids);
        $this->command = new DeleteProductCommand(caller('product:delete'), 'PROD1');
    });

    it('commits and answers a void success', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->with('PROD1')
            ->andReturn(Result::success($this->existing));
        $products->shouldReceive('delete')->once()->with('PROD1')->andReturn(Result::void());

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldReceive('invalidate')->once()->with(ViewCacheGroup::Product)
            ->andReturn(Result::void());

        $useCase = new DeleteProductUseCase(commitsOnce(), $views, $products, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue())->toBeNull();
    });

    it('surfaces the 404 and never deletes when the product is not there', function () {
        $missing = anError(404, 'no such product');

        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::failure($missing));
        $products->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteProductUseCase(rollsBackOnce(), $views, $products, $this->registrar);

        $result = $useCase->execute($this->command);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getErrorId())->toBe($missing);
    });

    it('rolls back and never invalidates when the delete fails', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldReceive('findById')->once()->andReturn(Result::success($this->existing));
        $products->shouldReceive('delete')->once()->andReturn(Result::failure(anError()));

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteProductUseCase(rollsBackOnce(), $views, $products, $this->registrar);

        expect($useCase->execute($this->command)->isSuccess())->toBeFalse();
    });

    it('refuses a caller without the permission before touching anything', function () {
        $products = Mockery::mock(IProductRepository::class);
        $products->shouldNotReceive('findById');
        $products->shouldNotReceive('delete');

        $views = Mockery::mock(IViewCacheRepository::class);
        $views->shouldNotReceive('invalidate');

        $useCase = new DeleteProductUseCase(untouchedUnitOfWork(), $views, $products,
            $this->registrar);

        $result = $useCase->execute(new DeleteProductCommand(stranger(), 'PROD1'));

        expect($result->isSuccess())->toBeFalse()
            ->and(codeOf($result))->toBe(403);
    });
})->group('App', 'UseCase');
