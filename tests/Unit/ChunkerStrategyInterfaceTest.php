<?php

declare(strict_types=1);

use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

describe('ChunkerStrategyInterface', function () {
    test('interface defines chunk method with correct signature', function () {
        $reflection = new ReflectionClass(ChunkerStrategyInterface::class);

        expect($reflection->isInterface())->toBeTrue();
        expect($reflection->hasMethod('chunk'))->toBeTrue();

        $method = $reflection->getMethod('chunk');
        expect($method->getNumberOfParameters())->toBe(3);
        expect($method->getNumberOfRequiredParameters())->toBe(3);
    });
});
