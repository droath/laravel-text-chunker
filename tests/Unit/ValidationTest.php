<?php

declare(strict_types=1);

use Droath\TextChunker\TextChunkerManager;
use Droath\TextChunker\Strategies\TokenStrategy;
use Droath\TextChunker\Exceptions\ChunkerException;

describe('Comprehensive Validation', function () {
    test('throws exception when size not set before chunk', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->strategy('character')->chunk('Test text'))
            ->toThrow(ChunkerException::class, 'Chunk size must be set before calling chunk()');
    });

    test('throws exception for size less than or equal to zero', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->strategy('character')->size(0)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Chunk size must be greater than zero');

        expect(fn () => $manager->strategy('character')->size(-5)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Chunk size must be greater than zero');
    });

    test('throws exception for overlap percentage below zero', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->strategy('character')->size(10)->overlap(-1)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Overlap percentage must be between 0 and 100');
    });

    test('throws exception for overlap percentage above 100', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->strategy('character')->size(10)->overlap(101)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Overlap percentage must be between 0 and 100');

        expect(fn () => $manager->strategy('character')->size(10)->overlap(150)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Overlap percentage must be between 0 and 100');
    });

    test('throws exception for empty text parameter', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->strategy('character')->size(10)->chunk(''))
            ->toThrow(ChunkerException::class, 'Text cannot be empty');
    });

    test('throws exception for unknown strategy name', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->strategy('nonexistent')->size(10)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Unknown chunking strategy: nonexistent');

        expect(fn () => $manager->strategy('invalid_strategy')->size(10)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Unknown chunking strategy: invalid_strategy');
    });

    test('throws exception when strategy not selected', function () {
        $manager = new TextChunkerManager();

        expect(fn () => $manager->size(10)->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Unknown chunking strategy: none');
    });

    test('exception message lists available strategies', function () {
        $manager = new TextChunkerManager();

        try {
            $manager->strategy('unknown')->size(10)->chunk('Test');
            expect(false)->toBeTrue('Exception should have been thrown');
        } catch (ChunkerException $e) {
            expect($e->getMessage())
                ->toContain('Available strategies:')
                ->toContain('character')
                ->toContain('token')
                ->toContain('sentence')
                ->toContain('markdown');
        }
    });

    test('throws exception for invalid token model', function () {
        $strategy = new TokenStrategy();

        expect(fn () => $strategy->chunk('Test text', 10, ['model' => 'invalid-model-xyz']))
            ->toThrow(ChunkerException::class, 'Unsupported model: invalid-model-xyz');
    });

    test('validates overlap range at execution time not during chain building', function () {
        $manager = new TextChunkerManager();

        // Setting overlap should not throw during chain building
        $fluent = $manager->strategy('character')->size(10)->overlap(150);
        expect($fluent)->toBeInstanceOf(TextChunkerManager::class);

        // Exception should be thrown only when chunk() is called
        expect(fn () => $fluent->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Overlap percentage must be between 0 and 100');
    });

    test('validates size at execution time not during chain building', function () {
        $manager = new TextChunkerManager();

        // Setting invalid size should not throw during chain building
        $fluent = $manager->strategy('character')->size(0);
        expect($fluent)->toBeInstanceOf(TextChunkerManager::class);

        // Exception should be thrown only when chunk() is called
        expect(fn () => $fluent->chunk('Test'))
            ->toThrow(ChunkerException::class, 'Chunk size must be greater than zero');
    });

    test('validates parameters provide actionable guidance', function () {
        // Test that error messages are descriptive and actionable

        // Test 1: Missing size
        $manager1 = new TextChunkerManager();
        try {
            $manager1->strategy('character')->chunk('Test');
        } catch (ChunkerException $e) {
            expect($e->getMessage())
                ->toContain('size')
                ->toContain('must be set');
        }

        // Test 2: Invalid overlap range
        $manager2 = new TextChunkerManager();
        try {
            $manager2->strategy('character')->size(10)->overlap(200)->chunk('Test');
        } catch (ChunkerException $e) {
            expect($e->getMessage())
                ->toContain('0 and 100');
        }

        // Test 3: Unknown strategy
        $manager3 = new TextChunkerManager();
        try {
            $manager3->strategy('nonexistent')->size(10)->chunk('Test');
        } catch (ChunkerException $e) {
            expect($e->getMessage())
                ->toContain('Unknown')
                ->toContain('Available strategies');
        }
    });
});
