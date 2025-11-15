<?php

declare(strict_types=1);

use Droath\TextChunker\Concerns\HasOverlap;

describe('HasOverlap Trait', function () {
    beforeEach(function () {
        $this->strategy = new class
        {
            use HasOverlap;
        };
    });

    test('setOverlap stores percentage value', function () {
        $this->strategy->setOverlap(25);

        expect($this->strategy->hasOverlap())->toBeTrue();
    });

    test('hasOverlap returns false when no overlap set', function () {
        expect($this->strategy->hasOverlap())->toBeFalse();
    });

    test('calculateOverlapAmount converts percentage to units correctly', function () {
        $this->strategy->setOverlap(50);

        expect($this->strategy->calculateOverlapAmount(100))->toBe(50);
        expect($this->strategy->calculateOverlapAmount(200))->toBe(100);
    });

    test('calculateOverlapAmount handles various percentages', function () {
        $testCases = [
            ['percentage' => 0, 'size' => 100, 'expected' => 0],
            ['percentage' => 10, 'size' => 100, 'expected' => 10],
            ['percentage' => 25, 'size' => 100, 'expected' => 25],
            ['percentage' => 50, 'size' => 200, 'expected' => 100],
            ['percentage' => 100, 'size' => 100, 'expected' => 100],
        ];

        foreach ($testCases as $case) {
            $this->strategy->setOverlap($case['percentage']);
            $result = $this->strategy->calculateOverlapAmount($case['size']);

            expect($result)->toBe($case['expected']);
        }
    });

    test('calculateOverlapAmount returns zero when no overlap set', function () {
        expect($this->strategy->calculateOverlapAmount(100))->toBe(0);
    });
});
