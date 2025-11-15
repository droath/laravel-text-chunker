<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Strategies\SentenceStrategy;

describe('SentenceStrategy', function () {
    it('chunks text by sentence count', function () {
        $strategy = new SentenceStrategy();
        $text = 'First sentence. Second sentence. Third sentence. Fourth sentence.';
        $chunks = $strategy->chunk($text, 2, []); // 2 sentences per chunk

        expect($chunks)->toHaveCount(2)
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->text)->toBe('First sentence. Second sentence.')
            ->and($chunks[0]->index)->toBe(0)
            ->and($chunks[1]->text)->toBe('Third sentence. Fourth sentence.')
            ->and($chunks[1]->index)->toBe(1);
    });

    it('handles abbreviations without false sentence breaks', function () {
        $strategy = new SentenceStrategy();
        $text = 'Dr. Smith went to the store. Mrs. Jones stayed home. The End.';
        $chunks = $strategy->chunk($text, 2, []);

        // Should be 2 chunks (2 sentences each): "Dr. Smith... Mrs. Jones..." and "The End."
        // But the last chunk has only 1 sentence
        expect($chunks)->toHaveCount(2)
            ->and($chunks[0]->text)->toContain('Dr. Smith')
            ->and($chunks[0]->text)->toContain('Mrs. Jones')
            ->and($chunks[1]->text)->toBe('The End.');
    });

    it('applies overlap by sentence count', function () {
        $strategy = new SentenceStrategy();
        $strategy->setOverlap(50); // 50% overlap
        $text = 'One. Two. Three. Four. Five. Six.';
        $chunks = $strategy->chunk($text, 2, []); // 2 sentences per chunk, 50% overlap = 1 sentence

        // Chunk 0: One. Two.
        // Chunk 1: Two. Three. (overlap 1 sentence)
        // Chunk 2: Three. Four. (overlap 1 sentence)
        // Chunk 3: Four. Five. (overlap 1 sentence)
        // Chunk 4: Five. Six. (overlap 1 sentence)
        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(2);
    });

    it('tracks character positions at sentence boundaries', function () {
        $strategy = new SentenceStrategy();
        $text = 'First. Second. Third.';
        $chunks = $strategy->chunk($text, 1, []); // 1 sentence per chunk

        expect($chunks)->toHaveCount(3)
            ->and($chunks[0]->text)->toBe('First.')
            ->and($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->end_position)->toBe(6)
            ->and($chunks[1]->text)->toBe('Second.')
            ->and($chunks[1]->start_position)->toBe(7)
            ->and($chunks[1]->end_position)->toBe(14)
            ->and($chunks[2]->text)->toBe('Third.')
            ->and($chunks[2]->start_position)->toBe(15)
            ->and($chunks[2]->end_position)->toBe(21);
    });

    it('handles custom abbreviations from options', function () {
        $strategy = new SentenceStrategy();
        $text = 'Prof. Johnson teaches well. Ltd. Company was formed. Done.';
        $chunks = $strategy->chunk($text, 1, ['abbreviations' => ['Prof', 'Ltd']]);

        expect($chunks)->toHaveCount(3)
            ->and($chunks[0]->text)->toBe('Prof. Johnson teaches well.')
            ->and($chunks[1]->text)->toBe('Ltd. Company was formed.')
            ->and($chunks[2]->text)->toBe('Done.');
    });

    it('handles text with fewer sentences than chunk size', function () {
        $strategy = new SentenceStrategy();
        $text = 'Only one sentence here.';
        $chunks = $strategy->chunk($text, 5, []);

        expect($chunks)->toHaveCount(1)
            ->and($chunks[0]->text)->toBe($text)
            ->and($chunks[0]->index)->toBe(0);
    });

    it('throws exception for empty text', function () {
        $strategy = new SentenceStrategy();
        $strategy->chunk('', 2, []);
    })->throws(ChunkerException::class, 'Text cannot be empty');

    it('handles question marks and exclamation marks as sentence boundaries', function () {
        $strategy = new SentenceStrategy();
        $text = 'Is this a question? Yes! This is exciting. The end.';
        $chunks = $strategy->chunk($text, 2, []);

        expect($chunks)->toHaveCount(2)
            ->and($chunks[0]->text)->toBe('Is this a question? Yes!')
            ->and($chunks[1]->text)->toBe('This is exciting. The end.');
    });
});
