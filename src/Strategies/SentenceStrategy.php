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
 * Supports configurable abbreviations and percentage-based overlap by sentence
 * count.
 */
class SentenceStrategy implements ChunkerStrategyInterface
{
    use HasOverlap;

    private const array DEFAULT_ABBREVIATIONS = ['Dr', 'Mr', 'Mrs', 'Ms', 'Prof', 'Ltd', 'Inc', 'Sr', 'Jr'];

    /**
     * Chunk text by sentence count.
     *
     * @param string $text The text to chunk
     * @param int $size The number of sentences per chunk
     * @param array<string, mixed> $options Options including 'abbreviations'
     *     array
     *
     * @return array<int, Chunk> Array of Chunk value objects
     *
     * @throws ChunkerException When the text is empty
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

        if ($step <= 0) {
            throw new ChunkerException(
                'Overlap percentage too high for given chunk size. Maximum overlap is '.
                (int) ((($size - 1) / $size) * 100).'% for size '.$size
            );
        }

        while ($position < $sentenceCount) {
            $sentenceSlice = array_slice($sentences, $position, $size);
            $chunkText = implode(' ', $sentenceSlice);

            $startPosition = $this->calculateSentencePosition($text, $sentences, $position);
            $endPosition = $startPosition + mb_strlen($chunkText);

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index,
                start_position: $startPosition,
                end_position: $endPosition
            );

            $index++;

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
     * @param array<int, string> $abbreviations Abbreviations to exclude from
     *     sentence breaks
     *
     * @return array<int, string> Array of sentences
     */
    private function splitIntoSentences(string $text, array $abbreviations): array
    {
        $abbreviationMap = [];
        foreach ($abbreviations as $index => $abbrev) {
            $placeholder = "___ABBREV{$index}___";
            $abbreviationMap[$placeholder] = $abbrev.'.';
            $text = str_replace($abbrev.'.', $placeholder, $text);
        }

        $pattern = '/(?<=[.!?])\s+/u';
        $sentences = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($sentences === false) {
            return [$text];
        }

        $sentences = array_map(function ($sentence) use ($abbreviationMap) {
            return str_replace(
                array_keys($abbreviationMap),
                array_values($abbreviationMap),
                $sentence
            );
        }, $sentences);

        $sentences = array_map('trim', $sentences);

        return array_values(array_filter($sentences, fn ($s) => $s !== ''));
    }

    /**
     * Calculate character position for a given sentence index in the original
     * text.
     *
     * @param string $originalText The original text
     * @param array<int, string> $sentences All sentences
     * @param int $sentenceIndex The sentence index to find the character
     *     position for
     *
     * @return int The character position in the original text
     */
    private function calculateSentencePosition(
        string $originalText,
        array $sentences,
        int $sentenceIndex
    ): int {
        if ($sentenceIndex === 0) {
            return 0;
        }

        $textUpToIndex = implode(' ', array_slice($sentences, 0, $sentenceIndex));

        $position = mb_strpos($originalText, $sentences[$sentenceIndex]);

        if ($position !== false) {
            return $position;
        }

        return mb_strlen($textUpToIndex) + ($sentenceIndex > 0 ? 1 : 0);
    }
}
