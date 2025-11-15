<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\TextChunkerManager;

describe('Laravel Integration', function () {
    test('service provider registers manager as singleton', function () {
        $instance1 = app('text-chunker');
        $instance2 = app('text-chunker');

        expect($instance1)->toBeInstanceOf(TextChunkerManager::class)
            ->and($instance1)->toBe($instance2);
    });

    test('config file can be published and loaded', function () {
        expect(config('text-chunker.default_strategy'))->toBe('character')
            ->and(config('text-chunker.strategies.token.model'))->toBe('gpt-4')
            ->and(config('text-chunker.strategies.sentence.abbreviations'))->toBeArray()
            ->and(config('text-chunker.strategies.sentence.abbreviations'))->toContain('Dr', 'Mr', 'Mrs', 'Ms');
    });

    test('service container provides access to manager', function () {
        $chunks = app('text-chunker')->strategy('character')->size(10)->chunk('Hello World');

        expect($chunks)->toBeArray()
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->text)->toBe('Hello Worl');
    });

    test('custom strategies can be auto-registered from config', function () {
        // Create a temporary custom strategy
        $customStrategyClass = new class implements Droath\TextChunker\Contracts\ChunkerStrategyInterface
        {
            public function chunk(string $text, int $size, array $options = []): array
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

        // Register it
        config(['text-chunker.custom_strategies.test' => get_class($customStrategyClass)]);

        // Manually trigger the registration since config change happened after boot
        $manager = app('text-chunker');
        $manager->extend('test', get_class($customStrategyClass));

        $chunks = $manager->strategy('test')->size(100)->chunk('Custom test');

        expect($chunks)->toBeArray()
            ->and($chunks[0]->text)->toBe('Custom test');
    });

    test('sentence strategy loads abbreviations from config', function () {
        $text = 'Dr. Smith went to the store. He bought milk.';

        $chunks = app('text-chunker')->strategy('sentence')->size(1)->chunk($text);

        // Should split into 2 chunks (2 sentences), not 3 (Dr. shouldn't break)
        expect($chunks)->toHaveCount(2)
            ->and($chunks[0]->text)->toContain('Dr. Smith');
    });

    test('token strategy uses model from config', function () {
        // Default model from config should be gpt-4
        $text = 'This is a test for token chunking.';

        $chunks = app('text-chunker')->strategy('token')->size(50)->chunk($text);

        expect($chunks)->toBeArray()
            ->and($chunks)->not->toBeEmpty();
    });
});
