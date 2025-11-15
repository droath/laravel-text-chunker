<?php

declare(strict_types=1);

namespace Droath\TextChunker\Contracts;

use Droath\TextChunker\DataObjects\Chunk;

/**
 * ChunkerStrategyInterface defines the contract for all text chunking strategies.
 *
 * Implementations should split text into chunks according to their specific
 * algorithm (character-based, token-based, sentence-based, markdown-aware, etc.)
 * and return an array of immutable Chunk value objects.
 */
interface ChunkerStrategyInterface
{
    /**
     * Chunk the given text into smaller segments.
     *
     * @param string $text The text to chunk
     * @param int $size The target size for each chunk (interpretation depends on strategy)
     * @param array<string, mixed> $options Strategy-specific options (e.g., model name, abbreviations)
     *
     * @return array<int, Chunk> Array of Chunk value objects with text, index, and position metadata
     *
     * @throws \Droath\TextChunker\Exceptions\ChunkerException When validation fails or chunking errors occur
     */
    public function chunk(string $text, int $size, array $options): array;
}
