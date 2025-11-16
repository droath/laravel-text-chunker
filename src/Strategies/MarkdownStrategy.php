<?php

declare(strict_types=1);

namespace Droath\TextChunker\Strategies;

use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Concerns\HasOverlap;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

/**
 * MarkdownStrategy splits text while preserving markdown structural elements.
 *
 * This strategy ensures that code blocks, headers, lists, tables, blockquotes,
 * and horizontal rules are never split in the middle. Elements remain whole
 * even if they exceed the chunk size. Uses character count as size metric.
 */
class MarkdownStrategy implements ChunkerStrategyInterface
{
    use HasOverlap;

    /**
     * Chunk markdown text while preserving structural elements.
     *
     * @param string $text The markdown text to chunk
     * @param int $size The target character count per chunk
     * @param array<string, mixed> $options Strategy-specific options (unused)
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

        $elements = $this->extractMarkdownElements($text);

        return $this->chunkElements($text, $elements, $size);
    }

    /**
     * Extract markdown structural elements with their positions.
     *
     * @param string $text The markdown text
     *
     * @return array<int, array{type: string, start: int, end: int,
     *     text:string}> Array of elements
     */
    private function extractMarkdownElements(string $text): array
    {
        $elements = [];

        $patterns = [
            'code_block' => '/```[\s\S]*?```/u',
            'header' => '/^#{1,6}\s+.+$/um',
            'list_item' => '/^[\*\-\+]\s+.+$/um',
            'blockquote' => '/^>.+$/um',
            'horizontal_rule' => '/^(\*{3,}|-{3,}|_{3,})$/um',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    [$matchText, $matchStart] = $match;
                    $matchEnd = $matchStart + mb_strlen($matchText);

                    $elements[] = [
                        'type' => $type,
                        'start' => $matchStart,
                        'end' => $matchEnd,
                        'text' => $matchText,
                    ];
                }
            }
        }

        usort($elements, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $elements;
    }

    /**
     * Chunk text while respecting element boundaries.
     *
     * @param string $text The original text
     * @param array<int, array{type: string, start: int, end: int,
     *     text:string}> $elements Markdown elements
     * @param int $size Target chunk size in characters
     *
     * @return array<int, Chunk> Array of chunks
     *
     * @throws ChunkerException
     */
    private function chunkElements(string $text, array $elements, int $size): array
    {
        $chunks = [];
        $index = 0;
        $position = 0;
        $textLength = mb_strlen($text);
        $overlapAmount = $this->calculateOverlapAmount($size);

        $initialStep = $size - $overlapAmount;
        if ($initialStep <= 0) {
            throw new ChunkerException(
                'Overlap percentage too high for given chunk size. Maximum overlap is '.
                (int) ((($size - 1) / $size) * 100).'% for size '.$size
            );
        }

        while ($position < $textLength) {
            $chunkEnd = min($position + $size, $textLength);

            $adjustedEnd = $this->findSafeBreakPoint($elements, $position, $chunkEnd);

            $chunkText = mb_substr($text, $position, $adjustedEnd - $position);

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index,
                start_position: $position,
                end_position: $adjustedEnd
            );

            $index++;

            if ($adjustedEnd >= $textLength) {
                break;
            }

            $step = $adjustedEnd - $position - (int) round(($adjustedEnd - $position) * ($overlapAmount / 100));
            $position += max($step, 1);
        }

        return $chunks;
    }

    /**
     * Find a safe break point that doesn't split markdown elements.
     *
     * @param array<int, array{type: string, start: int, end: int,
     *     text:string}> $elements Markdown elements
     * @param int $startPos Start position of chunk
     * @param int $endPos Proposed end position
     *
     * @return int Adjusted end position
     */
    private function findSafeBreakPoint(array $elements, int $startPos, int $endPos): int
    {
        foreach ($elements as $element) {
            if ($element['start'] < $endPos && $element['end'] > $endPos) {
                if ($element['start'] >= $startPos) {
                    return $element['end'];
                }

                return $element['start'];
            }

            if ($element['end'] > $endPos && $element['start'] > $startPos) {
                return $element['start'];
            }
        }

        return $endPos;
    }
}
