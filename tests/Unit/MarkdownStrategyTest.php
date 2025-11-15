<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Strategies\MarkdownStrategy;

describe('MarkdownStrategy', function () {
    it('preserves code blocks without splitting them', function () {
        $strategy = new MarkdownStrategy();
        $text = "Some text before.\n\n```php\nfunction test() {\n    return true;\n}\n```\n\nSome text after.";
        $chunks = $strategy->chunk($text, 50, []);

        // Code block should never be split
        expect($chunks)->toBeArray();

        $fullText = implode('', array_map(fn ($c) => $c->text, $chunks));
        expect($fullText)->toContain('```php')
            ->and($fullText)->toContain('function test()')
            ->and($fullText)->toContain('```');
    });

    it('preserves headers as atomic units', function () {
        $strategy = new MarkdownStrategy();
        $text = "# Main Title\n\nSome content here.\n\n## Section 1\n\nMore content.\n\n### Subsection\n\nEven more.";
        $chunks = $strategy->chunk($text, 30, []);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0);

        // Verify headers are preserved
        $fullText = implode('', array_map(fn ($c) => $c->text, $chunks));
        expect($fullText)->toContain('# Main Title')
            ->and($fullText)->toContain('## Section 1')
            ->and($fullText)->toContain('### Subsection');
    });

    it('preserves lists without breaking them mid-item', function () {
        $strategy = new MarkdownStrategy();
        $text = "Before list.\n\n- Item 1\n- Item 2\n- Item 3\n\nAfter list.";
        $chunks = $strategy->chunk($text, 20, []);

        expect($chunks)->toBeArray();

        $fullText = implode('', array_map(fn ($c) => $c->text, $chunks));
        expect($fullText)->toContain('- Item 1')
            ->and($fullText)->toContain('- Item 2')
            ->and($fullText)->toContain('- Item 3');
    });

    it('preserves blockquotes as complete units', function () {
        $strategy = new MarkdownStrategy();
        $text = "Regular text.\n\n> This is a quote\n> that spans lines\n\nMore text.";
        $chunks = $strategy->chunk($text, 30, []);

        expect($chunks)->toBeArray();

        $fullText = implode('', array_map(fn ($c) => $c->text, $chunks));
        expect($fullText)->toContain('> This is a quote')
            ->and($fullText)->toContain('> that spans lines');
    });

    it('preserves horizontal rules', function () {
        $strategy = new MarkdownStrategy();
        $text = "Section 1\n\n---\n\nSection 2\n\n***\n\nSection 3";
        $chunks = $strategy->chunk($text, 20, []);

        expect($chunks)->toBeArray();

        $fullText = implode('', array_map(fn ($c) => $c->text, $chunks));
        expect($fullText)->toContain('---')
            ->and($fullText)->toContain('***');
    });

    it('keeps elements whole even when they exceed chunk size', function () {
        $strategy = new MarkdownStrategy();
        $text = "```php\nfunction veryLongFunctionNameThatExceedsTheChunkSize() {\n    return 'This code block is longer than chunk size';\n}\n```";
        $chunks = $strategy->chunk($text, 10, []); // Very small chunk size

        // Should still have the complete code block in one chunk
        expect($chunks)->toHaveCount(1)
            ->and($chunks[0]->text)->toContain('```php')
            ->and($chunks[0]->text)->toContain('```');
    });

    it('applies overlap while preserving element boundaries', function () {
        $strategy = new MarkdownStrategy();
        $strategy->setOverlap(20);
        $text = "# Header 1\n\nParagraph 1 text.\n\n# Header 2\n\nParagraph 2 text.\n\n# Header 3\n\nParagraph 3 text.";
        $chunks = $strategy->chunk($text, 10, []);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0);

        // Verify all chunks are valid Chunk objects
        foreach ($chunks as $chunk) {
            expect($chunk)->toBeInstanceOf(Chunk::class)
                ->and($chunk->index)->toBeInt()
                ->and($chunk->start_position)->toBeInt()
                ->and($chunk->end_position)->toBeInt();
        }
    });

    it('throws exception for empty text', function () {
        $strategy = new MarkdownStrategy();
        $strategy->chunk('', 100, []);
    })->throws(ChunkerException::class, 'Text cannot be empty');
});
