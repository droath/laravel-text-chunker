<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;

describe('Chunk Value Object', function () {
    test('can be constructed with valid data', function () {
        $chunk = new Chunk(
            text: 'Hello World',
            index: 0,
            start_position: 0,
            end_position: 11
        );

        expect($chunk->text)->toBe('Hello World');
        expect($chunk->index)->toBe(0);
        expect($chunk->start_position)->toBe(0);
        expect($chunk->end_position)->toBe(11);
    });

    test('is immutable readonly class', function () {
        $reflection = new ReflectionClass(Chunk::class);

        expect($reflection->isReadOnly())->toBeTrue();
        expect($reflection->isFinal())->toBeTrue();
    });

    test('properties are readonly', function () {
        $reflection = new ReflectionClass(Chunk::class);

        $textProperty = $reflection->getProperty('text');
        $indexProperty = $reflection->getProperty('index');
        $startProperty = $reflection->getProperty('start_position');
        $endProperty = $reflection->getProperty('end_position');

        expect($textProperty->isReadOnly())->toBeTrue();
        expect($indexProperty->isReadOnly())->toBeTrue();
        expect($startProperty->isReadOnly())->toBeTrue();
        expect($endProperty->isReadOnly())->toBeTrue();
    });

    test('tracks multiple chunks with correct indices', function () {
        $chunks = [
            new Chunk('First chunk', 0, 0, 11),
            new Chunk('Second chunk', 1, 12, 24),
            new Chunk('Third chunk', 2, 25, 36),
        ];

        expect($chunks[0]->index)->toBe(0);
        expect($chunks[1]->index)->toBe(1);
        expect($chunks[2]->index)->toBe(2);
    });
});
