<?php

declare(strict_types=1);

use Domain\Enums\RiskClass;
use Domain\ID\IDatabaseIdGenerator;
use Domain\Models\IProduct;
use Domain\TableModules\Interno\ProductTM;
use Shared\Exceptions\Leaf;

describe('ProductTM', function () {
    beforeEach(function () {
        Leaf::flushProcessErrors();
        $ids = Mockery::mock(IDatabaseIdGenerator::class);
        $ids->shouldReceive('generate')->andReturn('PROD1');
        $this->tm = new ProductTM($ids);
    });

    it('creates a product with a generated id', function () {
        $result = $this->tm->create('Diesel', 0.85, RiskClass::Class3FlammableLiquids);

        expect($result->isSuccess())->toBeTrue();

        /** @var IProduct $product */
        $product = $result->getValue();

        expect($product->id)->toBe('PROD1')
            ->and($product->name)->toBe('Diesel')
            ->and($product->density)->toBe(0.85)
            ->and($product->riskClass)->toBe(RiskClass::Class3FlammableLiquids);
    });

    it('keeps the given id on update rather than generating a new one', function () {
        $result = $this->tm->update('EXISTING', 'Kerosene', 0.81, RiskClass::Class3FlammableLiquids);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getValue()->id)->toBe('EXISTING');
    });

    it('rejects an empty name with 422', function () {
        $result = $this->tm->create('   ', 0.85, RiskClass::None);

        expect($result->isSuccess())->toBeFalse();

        $context = Leaf::getError($result->getErrorId());

        expect($context?->code)->toBe(422)
            ->and($context?->details?->hasKey('name'))->toBeTrue();
    });

    it('rejects a non-positive density with 422', function (float $density) {
        $result = $this->tm->create('Diesel', $density, RiskClass::None);

        expect($result->isSuccess())->toBeFalse()
            ->and(Leaf::getError($result->getErrorId())?->details?->hasKey('density'))->toBeTrue();
    })->with([
        'zero' => 0.0,
        'negative' => -1.5,
    ]);

    it('rejects a name past 255 characters', function () {
        $result = $this->tm->create(str_repeat('a', 256), 0.85, RiskClass::None);

        expect(Leaf::getError($result->getErrorId())?->details?->hasKey('name'))->toBeTrue();
    });

    it('reports name and density together rather than the first', function () {
        $result = $this->tm->create('', 0.0, RiskClass::None);

        expect(Leaf::getError($result->getErrorId())?->details?->count())->toBe(2);
    });
})->group('Domain', 'TableModule');
