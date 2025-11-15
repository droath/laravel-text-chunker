<?php

declare(strict_types=1);

namespace Droath\TextChunker\Strategies;

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Concerns\HasOverlap;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

/**
 * CharacterStrategy splits text at exact character count boundaries.
 *
 * This strategy uses multibyte-safe functions to handle UTF-8 text correctly
 * and splits at exact character boundaries without regard to word breaks.
 * Supports percentage-based overlap for context preservation.
 */
class CharacterStrategy implements ChunkerStrategyInterface
{
    use HasOverlap;

    /**
     * Chunk text by character count.
     *
     * @param string $text The text to chunk
     * @param int $size The number of characters per chunk
     * @param array<string, mixed> $options Strategy-specific options (unused for character chunking)
     *
     * @return array<int, Chunk> Array of Chunk value objects
     *
     * @throws ChunkerException When text is empty
     */
    public function chunk(string $text, int $size, array $options): array
    {
        if ($text === '') {
            throw new ChunkerException('Text cannot be empty');
        }

        $textLength = mb_strlen($text);

        if ($textLength <= $size) {
            return [
                new Chunk(
                    text: $text,
                    index: 0,
                    start_position: 0,
                    end_position: $textLength
                ),
            ];
        }

        $chunks = [];
        $index = 0;
        $position = 0;
        $overlapAmount = $this->calculateOverlapAmount($size);
        $step = $size - $overlapAmount;

        // Guard: Ensure step is at least 1 to prevent infinite loops
        if ($step <= 0) {
            throw new ChunkerException(
                'Overlap percentage too high for given chunk size. Maximum overlap is '.
                (int) ((($size - 1) / $size) * 100).'% for size '.$size
            );
        }

        while ($position < $textLength) {
            $chunkText = mb_substr($text, $position, $size);
            $chunkLength = mb_strlen($chunkText);

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index,
                start_position: $position,
                end_position: $position + $chunkLength
            );

            $index++;

            // Move position forward by step size
            // Break if we've covered all text with this chunk
            if ($position + $chunkLength >= $textLength) {
                break;
            }

            $position += $step;
        }

        return $chunks;
    }
}
