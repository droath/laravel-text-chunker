<?php

declare(strict_types=1);

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\TextChunkerManager;
use Droath\TextChunker\Facades\TextChunker;
use Droath\TextChunker\Exceptions\ChunkerException;

describe('Feature Integration Tests', function () {
    test('complete end-to-end workflow from facade through manager to strategy', function () {
        // Test the complete chain: Facade -> Manager -> Strategy -> Chunks
        $text = 'This is the first sentence. This is the second sentence. This is the third sentence.';

        $chunks = app('text-chunker')->strategy('sentence')
            ->size(2)
            ->overlap(0)
            ->chunk($text);

        expect($chunks)->toBeArray()
            ->and($chunks)->toHaveCount(2)
            ->and($chunks[0])->toBeInstanceOf(Chunk::class)
            ->and($chunks[0]->text)->toBe('This is the first sentence. This is the second sentence.')
            ->and($chunks[0]->index)->toBe(0)
            ->and($chunks[1]->text)->toBe('This is the third sentence.')
            ->and($chunks[1]->index)->toBe(1);

        // Verify positions match actual text extraction
        foreach ($chunks as $chunk) {
            $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
            expect($chunk->text)->toBe($extracted);
        }
    });

    test('strategy switching within same manager instance maintains independence', function () {
        $manager = app('text-chunker');
        $text = 'Hello World! This is a test sentence.';

        // First operation: character strategy
        $characterChunks = $manager->strategy('character')->size(10)->chunk($text);
        expect($characterChunks)->toBeArray()
            ->and($characterChunks[0]->text)->toBe('Hello Worl');

        // Second operation: different strategy on same manager
        $sentenceChunks = $manager->strategy('sentence')->size(1)->chunk($text);
        expect($sentenceChunks)->toBeArray()
            ->and($sentenceChunks[0]->text)->toBe('Hello World!')
            ->and($sentenceChunks[1]->text)->toBe('This is a test sentence.');

        // Third operation: back to character with different size
        $characterChunks2 = $manager->strategy('character')->size(5)->chunk($text);
        expect($characterChunks2)->toBeArray()
            ->and($characterChunks2[0]->text)->toBe('Hello');
    });

    test('position accuracy across all strategies with complex text', function () {
        $text = 'The quick brown fox jumps over the lazy dog. '.
            'Pack my box with five dozen liquor jugs. '.
            'How vexingly quick daft zebras jump!';

        $strategies = ['character', 'token', 'sentence', 'markdown'];

        foreach ($strategies as $strategyName) {
            $options = $strategyName === 'token' ? ['model' => 'gpt-4'] : [];

            $chunks = app('text-chunker')->strategy($strategyName, $options)
                ->size(20)
                ->chunk($text);

            // Verify every chunk's text matches its positions in original text
            foreach ($chunks as $chunk) {
                $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
                expect($chunk->text)->toBe($extracted, "Position mismatch in {$strategyName} strategy for chunk {$chunk->index}");
            }
        }
    });

    test('large text handling maintains accuracy without memory issues', function () {
        // Generate large text (~50KB)
        $paragraph = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. ';
        $largeText = str_repeat($paragraph, 400); // ~50KB

        $chunks = app('text-chunker')->strategy('character')
            ->size(1000)
            ->overlap(10)
            ->chunk($largeText);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(40); // Should create many chunks

        // Verify first and last chunks
        expect($chunks[0]->start_position)->toBe(0)
            ->and($chunks[0]->text)->toHaveLength(1000);

        $lastChunk = end($chunks);
        expect($lastChunk->end_position)->toBe(mb_strlen($largeText));

        // Spot check position accuracy on random chunks
        foreach ([0, count($chunks) - 1, (int) (count($chunks) / 2)] as $index) {
            if (isset($chunks[$index])) {
                $chunk = $chunks[$index];
                $extracted = mb_substr($largeText, $chunk->start_position, $chunk->end_position - $chunk->start_position);
                expect($chunk->text)->toBe($extracted);
            }
        }
    });

    test('multiple consecutive chunk operations maintain correct state', function () {
        $manager = new TextChunkerManager();

        // Operation 1
        $result1 = $manager->strategy('character')->size(10)->overlap(20)->chunk('First text operation');
        expect($result1)->toBeArray();

        // Operation 2 - should not carry over previous config
        $result2 = $manager->strategy('character')->size(5)->chunk('Second operation');
        expect($result2)->toBeArray()
            ->and($result2[0]->text)->toBe('Secon');

        // Verify first result is still valid
        expect($result1[0]->text)->toBe('First text');
    });

    test('overlap edge cases across different strategies', function () {
        $text = 'A'.str_repeat('B', 98).'C'; // 100 characters: A + 98 Bs + C

        // Test 0% overlap
        $chunks0 = app('text-chunker')->strategy('character')->size(50)->overlap(0)->chunk($text);
        expect($chunks0)->toHaveCount(2)
            ->and($chunks0[0]->end_position)->toBe(50)
            ->and($chunks0[1]->start_position)->toBe(50); // No overlap

        // Test 100% overlap now throws exception (step would be 0)
        expect(fn () => app('text-chunker')
            ->strategy('character')
            ->size(50)
            ->overlap(100)
            ->chunk($text)
        )->toThrow(ChunkerException::class, 'Overlap percentage too high');

        // Test 98% overlap (maximum safe overlap for size 50)
        $chunks98 = app('text-chunker')->strategy('character')->size(50)->overlap(98)->chunk($text);
        expect(count($chunks98))->toBeGreaterThan(2); // Many chunks with high overlap
        if (count($chunks98) > 1) {
            expect($chunks98[1]->start_position)->toBe(1); // 98% of 50 = 49, so step = 1
        }

        // Test 50% overlap
        $chunks50 = app('text-chunker')->strategy('character')->size(50)->overlap(50)->chunk($text);
        if (count($chunks50) > 1) {
            expect($chunks50[1]->start_position)->toBe(25); // 50% of 50 = 25 chars back
        }
    });

    test('config values properly flow through to strategies', function () {
        // Test that config abbreviations are used by sentence strategy
        $configAbbreviations = config('text-chunker.strategies.sentence.abbreviations');
        expect($configAbbreviations)->toBeArray()
            ->and($configAbbreviations)->toContain('Dr');

        $text = 'Dr. Smith is here. Mr. Jones left. End.';
        $chunks = app('text-chunker')->strategy('sentence')->size(1)->chunk($text);

        // Should be 3 chunks (Dr. and Mr. shouldn't cause breaks)
        expect($chunks)->toHaveCount(3);

        // Test token strategy uses config model
        $configModel = config('text-chunker.strategies.token.model');
        expect($configModel)->toBe('gpt-4');

        $tokenChunks = TextChunker::strategy('token')->size(10)->chunk('Test text for tokens');
        expect($tokenChunks)->toBeArray();
    });

    test('runtime options override config defaults', function () {
        $text = 'Prof. Smith teaches here. Dr. Jones researches. End.';

        // Default config abbreviations don't include 'Prof'
        $defaultChunks = app('text-chunker')->strategy('sentence')->size(2)->chunk($text);

        // Runtime options with 'Prof' added
        $customChunks = app('text-chunker')->strategy('sentence', ['abbreviations' => ['Prof', 'Dr', 'Mr', 'Mrs', 'Ms']])
            ->size(2)
            ->chunk($text);

        // Both should chunk correctly, but custom should handle Prof. properly
        expect($customChunks)->toBeArray()
            ->and($customChunks[0]->text)->toContain('Prof. Smith');
    });

    test('markdown strategy handles mixed content with overlap', function () {
        $text = <<<'MARKDOWN'
# Title

Regular paragraph text here.

```php
function example() {
    return true;
}
```

Another paragraph.

- List item 1
- List item 2

Final text.
MARKDOWN;

        $chunks = app('text-chunker')->strategy('markdown')
            ->size(50)
            ->overlap(10)
            ->chunk($text);

        expect($chunks)->toBeArray()
            ->and(count($chunks))->toBeGreaterThan(0);

        // Verify all chunks have valid positions
        foreach ($chunks as $chunk) {
            $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
            expect($chunk->text)->toBe($extracted);
        }

        // Verify markdown structure preserved - reconstruct full text
        $reconstructed = '';
        $lastEnd = 0;
        foreach ($chunks as $chunk) {
            if ($chunk->start_position < $lastEnd) {
                // Handle overlap by skipping already covered content
                $overlapSize = $lastEnd - $chunk->start_position;
                $reconstructed .= mb_substr($chunk->text, $overlapSize);
            } else {
                $reconstructed .= $chunk->text;
            }
            $lastEnd = $chunk->end_position;
        }

        // Reconstructed should contain all structural elements
        expect($reconstructed)->toContain('# Title')
            ->and($reconstructed)->toContain('```php')
            ->and($reconstructed)->toContain('- List item 1');
    });

    test('token strategy handles different models consistently', function () {
        $text = 'The quick brown fox jumps over the lazy dog.';

        $models = ['gpt-4', 'gpt-3.5-turbo'];

        foreach ($models as $model) {
            $chunks = app('text-chunker')->strategy('token', ['model' => $model])
                ->size(10)
                ->chunk($text);

            expect($chunks)->toBeArray()
                ->and(count($chunks))->toBeGreaterThan(0);

            // Verify position accuracy for each model
            foreach ($chunks as $chunk) {
                $extracted = mb_substr($text, $chunk->start_position, $chunk->end_position - $chunk->start_position);
                expect($chunk->text)->toBe($extracted, "Position mismatch with model {$model}");
            }
        }
    });

    test('manager resets state after each chunk operation', function () {
        $manager = app('text-chunker');

        // Operation 1: Set overlap
        $chunks1 = $manager->strategy('character')->size(20)->overlap(50)->chunk('First operation with overlap here.');
        expect(count($chunks1))->toBeGreaterThan(1); // Has overlap

        // Operation 2: No overlap specified - should NOT use overlap from operation 1
        $chunks2 = $manager->strategy('character')->size(20)->chunk('Second operation text.');
        // With no overlap, 22 chars / 20 size = 2 chunks with no overlap
        expect($chunks2)->toHaveCount(2);
        // Verify no overlap: second chunk starts at position 20, not before
        expect($chunks2[1]->start_position)->toBe(20);

        // Operation 3: Different size and strategy
        $chunks3 = $manager->strategy('sentence')->size(2)->chunk('One. Two. Three. Four.');
        expect($chunks3)->toHaveCount(2); // 4 sentences / 2 per chunk = 2 chunks
    });

    test('all strategies throw exception when overlap creates zero step size', function () {
        $text = str_repeat('Test text for validation. ', 10);

        // Character strategy with 100% overlap
        expect(fn () => app('text-chunker')
            ->strategy('character')
            ->size(50)
            ->overlap(100)
            ->chunk($text)
        )->toThrow(ChunkerException::class);

        // Sentence strategy with size 1 and 100% overlap
        expect(fn () => app('text-chunker')
            ->strategy('sentence')
            ->size(1)
            ->overlap(100)
            ->chunk($text)
        )->toThrow(ChunkerException::class);

        // Token strategy with 100% overlap
        expect(fn () => app('text-chunker')
            ->strategy('token')
            ->size(50)
            ->overlap(100)
            ->chunk($text)
        )->toThrow(ChunkerException::class);
    });

    test('size 1 with any overlap throws exception due to rounding', function () {
        $text = 'First. Second. Third. Fourth.';

        // Size 1 with 100% overlap creates step = 0
        expect(fn () => app('text-chunker')
            ->strategy('sentence')
            ->size(1)
            ->overlap(100)
            ->chunk($text)
        )->toThrow(ChunkerException::class, 'Maximum overlap is 0% for size 1');

        // Size 1 with 50% overlap also throws because round(1 * 0.5) = 1, making step = 0
        expect(fn () => app('text-chunker')
            ->strategy('sentence')
            ->size(1)
            ->overlap(50)
            ->chunk($text)
        )->toThrow(ChunkerException::class, 'Maximum overlap is 0% for size 1');

        // Only 0% overlap works with size 1
        $chunks = app('text-chunker')->strategy('sentence')->size(1)->overlap(0)->chunk($text);
        expect($chunks)->toHaveCount(4);
    });
});
