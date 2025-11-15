<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\TextChunkerManager;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

describe('TextChunkerManager', function () {
    test('registers built-in strategies automatically', function () {
        $manager = new TextChunkerManager();

        // Should be able to use built-in strategies without registration
        $result = $manager->strategy('character')->size(10)->chunk('Hello World');

        expect($result)->toBeArray();
        expect($result[0])->toBeInstanceOf(Chunk::class);
    });

    test('registers custom strategy via extend method', function () {
        $manager = new TextChunkerManager();

        $customStrategy = new class implements ChunkerStrategyInterface
        {
            public function chunk(string $text, int $size, array $options): array
            {
                return [
                    new Chunk(
                        text: $text,
                        index: 0,
                        start_position: 0,
                        end_position: mb_strlen($text)
                    ),
                ];
            }
        };

        $manager->extend('custom', $customStrategy::class);

        $result = $manager->strategy('custom')->size(100)->chunk('Test text');

        expect($result)->toBeArray();
        expect($result[0])->toBeInstanceOf(Chunk::class);
    });

    test('throws exception when extending with non-strategy class', function () {
        $manager = new TextChunkerManager();

        $manager->extend('invalid', stdClass::class);
    })->throws(ChunkerException::class, 'must implement ChunkerStrategyInterface');

    test('fluent API chains methods correctly', function () {
        $manager = new TextChunkerManager();

        $result = $manager
            ->strategy('character')
            ->size(5)
            ->overlap(20)
            ->chunk('Hello World');

        expect($result)->toBeArray();
        expect($result)->not->toBeEmpty();
        expect($result[0])->toBeInstanceOf(Chunk::class);
    });

    test('validates size is set before chunking', function () {
        $manager = new TextChunkerManager();

        $manager->strategy('character')->chunk('Test');
    })->throws(ChunkerException::class, 'Chunk size must be set');

    test('validates size is greater than zero', function () {
        $manager = new TextChunkerManager();

        $manager->strategy('character')->size(0)->chunk('Test');
    })->throws(ChunkerException::class, 'Chunk size must be greater than zero');

    test('validates overlap percentage is within 0-100 range', function () {
        $manager = new TextChunkerManager();

        $manager->strategy('character')->size(10)->overlap(150)->chunk('Test');
    })->throws(ChunkerException::class, 'Overlap percentage must be between 0 and 100');

    test('validates text is non-empty', function () {
        $manager = new TextChunkerManager();

        $manager->strategy('character')->size(10)->chunk('');
    })->throws(ChunkerException::class, 'Text cannot be empty');

    test('validates strategy exists', function () {
        $manager = new TextChunkerManager();

        $manager->strategy('nonexistent')->size(10)->chunk('Test');
    })->throws(ChunkerException::class, 'Unknown chunking strategy: nonexistent');

    test('passes overlap to strategy when configured', function () {
        $manager = new TextChunkerManager();

        $result = $manager
            ->strategy('character')
            ->size(10)
            ->overlap(50)
            ->chunk('This is a test text for overlap');

        expect($result)->toBeArray();
        expect(count($result))->toBeGreaterThan(1);

        // With 50% overlap, chunks should overlap
        // Verify that subsequent chunks start before previous ends
        if (count($result) > 1) {
            $firstChunkEnd = $result[0]->end_position;
            $secondChunkStart = $result[1]->start_position;
            expect($secondChunkStart)->toBeLessThan($firstChunkEnd);
        }
    });

    test('passes options to strategy', function () {
        $manager = new TextChunkerManager();

        // Token strategy accepts 'model' option
        $result = $manager
            ->strategy('token', ['model' => 'gpt-4'])
            ->size(50)
            ->chunk('This is a test text for token chunking.');

        expect($result)->toBeArray();
        expect($result[0])->toBeInstanceOf(Chunk::class);
    });

    test('lists available strategies in error message', function () {
        $manager = new TextChunkerManager();

        try {
            $manager->strategy('invalid')->size(10)->chunk('Test');
            expect(false)->toBeTrue(); // Should not reach here
        } catch (ChunkerException $e) {
            expect($e->getMessage())->toContain('character');
            expect($e->getMessage())->toContain('token');
            expect($e->getMessage())->toContain('sentence');
            expect($e->getMessage())->toContain('markdown');
        }
    });
});
