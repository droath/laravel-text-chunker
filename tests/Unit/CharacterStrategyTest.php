<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Strategies\CharacterStrategy;

describe('CharacterStrategy', function () {
    it('chunks text at exact character boundaries', function () {
        $strategy = new CharacterStrategy();
        $text = 'Hello World! This is a test.';
        $chunks = $strategy->chunk($text, 10, []);

        expect($chunks)->toHaveCount(3)
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->text)->toBe('Hello Worl')
            ->and($chunks[0]->index)->toBe(0)
            ->and($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->end_position)->toBe(10)
            ->and($chunks[1]->text)->toBe('d! This is')
            ->and($chunks[1]->index)->toBe(1)
            ->and($chunks[1]->start_position)->toBe(10)
            ->and($chunks[1]->end_position)->toBe(20)
            ->and($chunks[2]->text)->toBe(' a test.')
            ->and($chunks[2]->index)->toBe(2)
            ->and($chunks[2]->start_position)->toBe(20)
            ->and($chunks[2]->end_position)->toBe(28);
    });

    it('handles multibyte UTF-8 characters correctly', function () {
        $strategy = new CharacterStrategy();
        $text = 'Hello 世界! 你好'; // "Hello World! Hello" in Chinese
        $chunks = $strategy->chunk($text, 8, []);

        expect($chunks)->toHaveCount(2)
            ->and($chunks[0]->text)->toBe('Hello 世界')
            ->and(mb_strlen($chunks[0]->text))->toBe(8)
            ->and($chunks[1]->text)->toBe('! 你好')
            ->and(mb_strlen($chunks[1]->text))->toBe(4);
    });

    it('applies percentage-based overlap between chunks', function () {
        $strategy = new CharacterStrategy();
        $strategy->setOverlap(50); // 50% overlap
        $text = 'ABCDEFGHIJKLMNOPQRST'; // 20 characters
        $chunks = $strategy->chunk($text, 10, []);

        // With 50% overlap on size 10: overlap = 5 characters
        // Chunk 0: ABCDEFGHIJ (0-10)
        // Chunk 1: FGHIJKLMNO (5-15) - starts 5 chars back
        // Chunk 2: KLMNOPQRST (10-20) - starts 5 chars back
        expect($chunks)->toHaveCount(3)
            ->and($chunks[0]->text)->toBe('ABCDEFGHIJ')
            ->and($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->end_position)->toBe(10)
            ->and($chunks[1]->text)->toBe('FGHIJKLMNO')
            ->and($chunks[1]->start_position)->toBe(5)
            ->and($chunks[1]->end_position)->toBe(15)
            ->and($chunks[2]->text)->toBe('KLMNOPQRST')
            ->and($chunks[2]->start_position)->toBe(10)
            ->and($chunks[2]->end_position)->toBe(20);
    });

    it('handles text shorter than chunk size', function () {
        $strategy = new CharacterStrategy();
        $text = 'Short';
        $chunks = $strategy->chunk($text, 100, []);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0]->text)->toBe('Short')
            ->and($chunks[0]->index)->toBe(0)
            ->and($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->end_position)->toBe(5);
    });

    it('handles text that is an exact multiple of chunk size', function () {
        $strategy = new CharacterStrategy();
        $text = 'ABCDEFGHIJ'; // Exactly 10 characters
        $chunks = $strategy->chunk($text, 5, []);

        expect($chunks)->toHaveCount(2)
            ->and($chunks[0]->text)->toBe('ABCDE')
            ->and($chunks[1]->text)->toBe('FGHIJ');
    });

    it('throws exception for empty text', function () {
        $strategy = new CharacterStrategy();
        $strategy->chunk('', 10, []);
    })->throws(ChunkerException::class, 'Text cannot be empty');

    it('tracks zero-based positions accurately across all chunks', function () {
        $strategy = new CharacterStrategy();
        $text = '0123456789ABCDEFGHIJ'; // 20 characters
        $chunks = $strategy->chunk($text, 7, []);

        expect($chunks)->toHaveCount(3)
            ->and($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->end_position)->toBe(7)
            ->and($chunks[1]->start_position)->toBe(7)
            ->and($chunks[1]->end_position)->toBe(14)
            ->and($chunks[2]->start_position)->toBe(14)
            ->and($chunks[2]->end_position)->toBe(20);
    });

    it('applies 20% overlap correctly', function () {
        $strategy = new CharacterStrategy();
        $strategy->setOverlap(20);
        $text = 'ABCDEFGHIJKLMNOPQRST'; // 20 characters
        $chunks = $strategy->chunk($text, 10, []);

        // With 20% overlap on size 10: overlap = 2 characters
        expect($chunks)->toHaveCount(3)
            ->and($chunks[0]->text)->toBe('ABCDEFGHIJ')
            ->and($chunks[1]->text)->toBe('IJKLMNOPQR')
            ->and($chunks[1]->start_position)->toBe(8)
            ->and($chunks[2]->text)->toBe('QRST')
            ->and($chunks[2]->start_position)->toBe(16);
    });
});
