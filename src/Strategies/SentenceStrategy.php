<?php

declare(strict_types=1);

namespace Droath\TextChunker\Strategies;

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Concerns\HasOverlap;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

/**
 * SentenceStrategy splits text by sentence count using regex-based detection.
 *
 * This strategy detects sentence boundaries using periods, question marks, and
 * exclamation marks while avoiding false breaks on common abbreviations.
 * Supports configurable abbreviations and percentage-based overlap by sentence count.
 */
class SentenceStrategy implements ChunkerStrategyInterface
{
    use HasOverlap;

    private const DEFAULT_ABBREVIATIONS = ['Dr', 'Mr', 'Mrs', 'Ms', 'Prof', 'Ltd', 'Inc', 'Sr', 'Jr'];

    /**
     * Chunk text by sentence count.
     *
     * @param string $text The text to chunk
     * @param int $size The number of sentences per chunk
     * @param array<string, mixed> $options Options including 'abbreviations' array
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

        $abbreviations = $options['abbreviations']
            ?? config('text-chunker.strategies.sentence.abbreviations', self::DEFAULT_ABBREVIATIONS);
        $sentences = $this->splitIntoSentences($text, $abbreviations);

        $sentenceCount = count($sentences);

        if ($sentenceCount <= $size) {
            return [
                new Chunk(
                    text: $text,
                    index: 0,
                    start_position: 0,
                    end_position: mb_strlen($text)
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

        while ($position < $sentenceCount) {
            // Extract sentence slice
            $sentenceSlice = array_slice($sentences, $position, $size);
            $chunkText = implode(' ', $sentenceSlice);

            // Calculate character positions in original text
            $startPosition = $this->calculateSentencePosition($text, $sentences, $position);
            $endPosition = $startPosition + mb_strlen($chunkText);

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index,
                start_position: $startPosition,
                end_position: $endPosition
            );

            $index++;

            // Break if we've covered all sentences
            if ($position + count($sentenceSlice) >= $sentenceCount) {
                break;
            }

            $position += $step;
        }

        return $chunks;
    }

    /**
     * Split text into individual sentences.
     *
     * @param string $text The text to split
     * @param array<int, string> $abbreviations Abbreviations to exclude from sentence breaks
     *
     * @return array<int, string> Array of sentences
     */
    private function splitIntoSentences(string $text, array $abbreviations): array
    {
        // Replace abbreviations with placeholders to prevent false splits
        $abbreviationMap = [];
        foreach ($abbreviations as $index => $abbrev) {
            $placeholder = "___ABBREV{$index}___";
            $abbreviationMap[$placeholder] = $abbrev.'.';
            $text = str_replace($abbrev.'.', $placeholder, $text);
        }

        // Split on sentence endings: . ! ? followed by space or end of string
        $pattern = '/(?<=[.!?])\s+/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($sentences === false) {
            return [$text];
        }

        // Restore abbreviations
        $sentences = array_map(function ($sentence) use ($abbreviationMap) {
            return str_replace(array_keys($abbreviationMap), array_values($abbreviationMap), $sentence);
        }, $sentences);

        // Trim and filter
        $sentences = array_map('trim', $sentences);

        return array_values(array_filter($sentences, fn ($s) => $s !== ''));
    }

    /**
     * Calculate character position for a given sentence index in the original text.
     *
     * @param string $originalText The original text
     * @param array<int, string> $sentences All sentences
     * @param int $sentenceIndex The sentence index to find character position for
     *
     * @return int The character position in the original text
     */
    private function calculateSentencePosition(string $originalText, array $sentences, int $sentenceIndex): int
    {
        if ($sentenceIndex === 0) {
            return 0;
        }

        // Find the position by searching for the sentence in the original text
        // Build the text up to this sentence index
        $textUpToIndex = implode(' ', array_slice($sentences, 0, $sentenceIndex));

        // Find where the next sentence starts in the original text
        $position = mb_strpos($originalText, $sentences[$sentenceIndex]);

        if ($position !== false) {
            return $position;
        }

        // Fallback: calculate based on cumulative length
        return mb_strlen($textUpToIndex) + ($sentenceIndex > 0 ? 1 : 0);
    }
}
