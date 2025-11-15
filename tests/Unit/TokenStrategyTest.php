<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Strategies\TokenStrategy;
use Droath\TextChunker\Exceptions\ChunkerException;

describe('TokenStrategy', function () {
    it('chunks text by token count using tiktoken', function () {
        $strategy = new TokenStrategy();
        $text = 'Hello world! This is a test of token-based chunking for AI applications.';
        $chunks = $strategy->chunk($text, 10, ['model' => 'gpt-4']);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0)
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->index)->toBe(0);
    });

    it('supports different models via options', function () {
        $strategy = new TokenStrategy();
        $text = 'Testing GPT-3.5 Turbo model with some sample text for chunking.';

        $chunks = $strategy->chunk($text, 5, ['model' => 'gpt-3.5-turbo']);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0)
            ->and($chunks[0])->toBeInstanceOf(Chunk::class);
    });

    it('applies overlap at token boundaries', function () {
        $strategy = new TokenStrategy();
        $strategy->setOverlap(50); // 50% token overlap
        $text = 'This is a test sentence for token overlap functionality testing.';

        $chunks = $strategy->chunk($text, 10, ['model' => 'gpt-4']);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(1);

        // Verify chunks have proper indices
        foreach ($chunks as $index => $chunk) {
            expect($chunk->index)->toBe($index);
        }
    });

    it('calculates character positions accurately', function () {
        $strategy = new TokenStrategy();
        $text = 'The quick brown fox jumps over the lazy dog.';
        $chunks = $strategy->chunk($text, 5, ['model' => 'gpt-4']);

        expect($chunks)->toBeArray();

        foreach ($chunks as $chunk) {
            // Verify positions are valid
            expect($chunk->start_position)->toBeGreaterThanOrEqual(0)
                ->and($chunk->end_position)->toBeGreaterThan($chunk->start_position)
                ->and($chunk->end_position)->toBeLessThanOrEqual(mb_strlen($text));

            // Verify text matches positions
            $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
            expect($chunk->text)->toBe($extracted);
        }
    });

    it('throws exception for unsupported model', function () {
        $strategy = new TokenStrategy();
        $strategy->chunk('Test text', 10, ['model' => 'unsupported-model-123']);
    })->throws(ChunkerException::class);

    it('handles text shorter than token limit', function () {
        $strategy = new TokenStrategy();
        $text = 'Short text.';
        $chunks = $strategy->chunk($text, 100, ['model' => 'gpt-4']);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0]->text)->toBe($text)
            ->and($chunks[0]->index)->toBe(0)
            ->and($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->end_position)->toBe(mb_strlen($text));
    });

    it('uses default model when not specified', function () {
        $strategy = new TokenStrategy();
        $text = 'Testing default model configuration for token chunking.';
        $chunks = $strategy->chunk($text, 10, []);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0);
    });

    it('throws exception for empty text', function () {
        $strategy = new TokenStrategy();
        $strategy->chunk('', 10, ['model' => 'gpt-4']);
    })->throws(ChunkerException::class, 'Text cannot be empty');
});
