<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Facades\TextChunker;
use Droath\TextChunker\Exceptions\ChunkerException;

describe('Strategic Gap Coverage Tests', function () {
    test('facade provides static access to chunking functionality', function () {
        $text = 'Testing facade static access for chunking operations.';

        $chunks = TextChunker::strategy('character')
            ->size(20)
            ->overlap(10)
            ->chunk($text);

        expect($chunks)->toBeArray()
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->text)->toBe('Testing facade stati');
    });

    test('chunks can be reassembled to reconstruct original text without overlap', function () {
        $originalText = 'The quick brown fox jumps over the lazy dog. Pack my box with five dozen liquor jugs.';

        $strategies = [
            'character' => [],
            'sentence' => [],
            'markdown' => [],
        ];

        foreach ($strategies as $strategy => $options) {
            $chunks = app('text-chunker')->strategy($strategy, $options)
                ->size(20)
                ->overlap(0) // No overlap for clean reconstruction
                ->chunk($originalText);

            $reconstructed = implode('', array_map(fn ($chunk) => $chunk->text, $chunks));

            expect($reconstructed)->toBe($originalText, "Failed to reconstruct text using {$strategy} strategy");
        }
    });

    test('chunks with overlap can be reconstructed to original text using position tracking', function () {
        $originalText = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $chunks = app('text-chunker')->strategy('character')
            ->size(10)
            ->overlap(30)
            ->chunk($originalText);

        // Reconstruct using positions instead of just concatenating
        $reconstructed = '';
        $lastEnd = 0;

        foreach ($chunks as $chunk) {
            if ($chunk->start_position >= $lastEnd) {
                // No overlap or gap, append entire chunk text
                $reconstructed .= $chunk->text;
            } else {
                // Handle overlap by skipping already covered content
                $overlapSize = $lastEnd - $chunk->start_position;
                $reconstructed .= mb_substr($chunk->text, $overlapSize);
            }
            $lastEnd = $chunk->end_position;
        }

        expect($reconstructed)->toBe($originalText);
    });

    test('strategies handle emoji and complex Unicode characters correctly', function () {
        $text = 'Hello 👋 World 🌍! Testing emoji 😀🎉 and symbols ⚡️💡 in text.';

        $strategies = ['character', 'sentence', 'markdown'];

        foreach ($strategies as $strategyName) {
            $chunks = app('text-chunker')->strategy($strategyName)
                ->size(15)
                ->chunk($text);

            // Verify position accuracy with Unicode
            foreach ($chunks as $chunk) {
                $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
                expect($chunk->text)->toBe($extracted, "Unicode handling failed in {$strategyName} strategy");
            }

            // Verify complete text coverage
            $fullText = '';
            foreach ($chunks as $chunk) {
                $fullText .= $chunk->text;
            }
            expect($fullText)->toContain('👋')
                ->and($fullText)->toContain('🌍')
                ->and($fullText)->toContain('😀');
        }
    });

    test('manager instance reuse does not leak configuration between operations', function () {
        $manager = app('text-chunker');
        $text = 'Test text for configuration isolation.';

        // Operation 1: With overlap
        $chunks1 = $manager->strategy('character')->size(10)->overlap(50)->chunk($text);
        expect(count($chunks1))->toBeGreaterThan(2); // Should have overlap

        // Operation 2: Without specifying overlap (should default to no overlap)
        $chunks2 = $manager->strategy('character')->size(10)->chunk('New operation text.');
        // The second operation should not have overlap from first operation
        expect($chunks2[0]->text)->toBe('New operat');

        // Operation 3: Different strategy and size
        $chunks3 = $manager->strategy('sentence')->size(1)->chunk('First. Second. Third.');
        expect($chunks3)->toHaveCount(3);

        // Verify first result wasn't affected
        expect($chunks1[0]->text)->toBe('Test text ');
    });

    test('very small chunk size with high overlap does not create infinite loops', function () {
        $text = 'Small text for testing edge case.';

        // Edge case: 2 character chunks with 50% overlap
        $chunks = app('text-chunker')->strategy('character')
            ->size(2)
            ->overlap(50)
            ->chunk($text);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0)
            ->and(count($chunks))->toBeLessThan(100); // Ensure it's finite

        // Verify coverage
        $lastChunk = end($chunks);
        expect($lastChunk->end_position)->toBe(mb_strlen($text));
    });

    test('strategies maintain independence when used through same manager', function () {
        $manager = app('text-chunker');
        $text = 'Testing strategy independence with shared manager instance.';

        // Use multiple strategies in quick succession
        $charChunks = $manager->strategy('character')->size(20)->chunk($text);
        $sentChunks = $manager->strategy('sentence')->size(1)->chunk($text);
        $tokenChunks = $manager->strategy('token')->size(10)->chunk($text);

        // Each should produce different results
        expect(count($charChunks))->not->toBe(count($sentChunks))
            ->and(count($charChunks))->not->toBe(count($tokenChunks))
            ->and($charChunks[0]->text)->not->toBe($sentChunks[0]->text);

        // All should have correct chunk structure
        expect($charChunks[0])->toBeInstanceOf(Chunk::class)
            ->and($sentChunks[0])->toBeInstanceOf(Chunk::class)
            ->and($tokenChunks[0])->toBeInstanceOf(Chunk::class);
    });

    test('error recovery allows continued use of manager after failed operation', function () {
        $manager = app('text-chunker');

        // First operation fails due to invalid configuration
        try {
            $manager->strategy('character')->size(0)->chunk('Test');
            expect(false)->toBeTrue('Exception should have been thrown');
        } catch (ChunkerException $e) {
            expect($e->getMessage())->toContain('greater than zero');
        }

        // Manager should still work after exception
        $chunks = $manager->strategy('character')->size(10)->chunk('Valid operation');

        expect($chunks)->toBeArray()
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->text)->toBe('Valid oper');
    });

    test('all strategies produce consistent chunk indices regardless of overlap', function () {
        $text = 'First sentence here. Second sentence here. Third sentence here.';
        $strategies = ['character', 'sentence', 'token', 'markdown'];

        foreach ($strategies as $strategyName) {
            $options = $strategyName === 'token' ? ['model' => 'gpt-4'] : [];

            // Test without overlap
            $chunks = app('text-chunker')->strategy($strategyName, $options)
                ->size(10)
                ->overlap(0)
                ->chunk($text);

            foreach ($chunks as $index => $chunk) {
                expect($chunk->index)->toBe($index, "Index mismatch in {$strategyName} without overlap");
            }

            // Test with overlap
            $chunksOverlap = app('text-chunker')->strategy($strategyName, $options)
                ->size(10)
                ->overlap(20)
                ->chunk($text);

            foreach ($chunksOverlap as $index => $chunk) {
                expect($chunk->index)->toBe($index, "Index mismatch in {$strategyName} with overlap");
            }
        }
    });

    test('edge case handling for text with only whitespace and special characters', function () {
        $testCases = [
            'Multiple  spaces   between    words' => 'character',
            "Newlines\n\nand\n\ntabs\t\there" => 'character',
            '     ' => 'character', // Only spaces
            ".\n.\n." => 'sentence', // Only sentence terminators
        ];

        foreach ($testCases as $text => $strategy) {
            $chunks = app('text-chunker')->strategy($strategy)
                ->size(5)
                ->chunk($text);

            expect($chunks)->toBeArray()
                ->and(count($chunks))->toBeGreaterThan(0);

            // Verify position accuracy
            foreach ($chunks as $chunk) {
                $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
                expect($chunk->text)->toBe($extracted, "Whitespace handling failed for: {$text}");
            }
        }
    });
});
