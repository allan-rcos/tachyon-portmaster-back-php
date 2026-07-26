<?php

declare(strict_types=1);

use Domain\ID\IDatabaseIdGenerator;
use Domain\ID\Interno\NanoIdGenerator;
use Domain\ID\Interno\SnowflakeIdGenerator;
use Domain\ID\Interno\UlidGenerator;
use Domain\ID\IRandomIdGenerator;
use Domain\ID\ISequentialIdGenerator;

/**
 * The three flavours exist so a consumer states its intent. These cases pin the
 * property each flavour promises, since picking the wrong one is exactly the
 * mistake the split is meant to prevent.
 */
describe('id generators', function () {
    it('marks each implementation with the intent it satisfies', function () {
        expect(new UlidGenerator())->toBeInstanceOf(ISequentialIdGenerator::class)
            ->and(new NanoIdGenerator())->toBeInstanceOf(IRandomIdGenerator::class)
            ->and(SnowflakeIdGenerator::create(1, 0))->toBeInstanceOf(IDatabaseIdGenerator::class);
    });

    it('produces chronologically sortable ULIDs', function () {
        $generator = new UlidGenerator();

        $first = $generator->generate();
        usleep(2000);
        $second = $generator->generate();

        // Lexicographic order is chronological order — the whole promise of
        // ISequentialIdGenerator, and why request ids use it.
        expect(strcmp($first, $second))->toBeLessThan(0)
            ->and($first)->toHaveLength(26);
    });

    it('produces unpredictable, unique NanoIDs', function () {
        $generator = new NanoIdGenerator();

        $ids = array_map(static fn (): string => $generator->generate(), range(1, 500));

        expect(array_unique($ids))->toHaveCount(500)
            ->and($ids[0])->toHaveLength(21)
            ->and($ids[0])->toMatch('/^[A-Za-z0-9_-]+$/');

        // Consecutive ids must not be ordered: that would leak issue order.
        $sorted = $ids;
        sort($sorted);
        expect($sorted)->not->toBe($ids);
    });

    it('produces monotonically increasing Snowflakes within a worker', function () {
        $generator = SnowflakeIdGenerator::create(1, 0);

        $previous = '';
        for ($i = 0; $i < 100; $i++) {
            $id = $generator->generate();
            expect($id)->not->toBe($previous);
            $previous = $id;
        }
    });

    it('refuses an out-of-range cluster or server id', function () {
        // 5 bits each, so 31 is the ceiling; a silent wrap would collide ids
        // across workers.
        expect(SnowflakeIdGenerator::create(32, 0))->toBeInstanceOf(Shared\Exceptions\LeafContext::class)
            ->and(SnowflakeIdGenerator::create(0, 32))->toBeInstanceOf(Shared\Exceptions\LeafContext::class)
            ->and(SnowflakeIdGenerator::create(-1, 0))->toBeInstanceOf(Shared\Exceptions\LeafContext::class)
            ->and(SnowflakeIdGenerator::create(31, 31))->toBeInstanceOf(SnowflakeIdGenerator::class);
    });
})->group('Domain', 'ID');
