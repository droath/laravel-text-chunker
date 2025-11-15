<?php

declare(strict_types=1);

namespace Droath\TextChunker\DataObjects;

/**
 * Chunk represents an immutable text chunk with metadata.
 *
 * This value object contains the chunked text along with its position
 * in the sequence and character offsets in the original text.
 */
final readonly class Chunk
{
    /**
     * Create a new Chunk instance.
     *
     * @param string $text The chunked text content
     * @param int $index Zero-based index of this chunk in the sequence (0 = first chunk)
     * @param int $start_position Character offset where this chunk starts in original text (inclusive)
     * @param int $end_position Character offset where this chunk ends in original text (exclusive)
     */
    public function __construct(
        public string $text,
        public int $index,
        public int $start_position,
        public int $end_position,
    ) {}
}
