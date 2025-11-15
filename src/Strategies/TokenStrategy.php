<?php

declare(strict_types=1);

namespace Droath\TextChunker\Strategies;

use Exception;
use Yethee\Tiktoken\EncoderProvider;
use Droath\TextChunker\DataObjects\Chunk;
use Droath\TextChunker\Concerns\HasOverlap;
use Droath\TextChunker\Exceptions\ChunkerException;
use Droath\TextChunker\Contracts\ChunkerStrategyInterface;

/**
 * TokenStrategy splits text by token count using OpenAI's tiktoken library.
 *
 * This strategy encodes text into tokens, chunks the token arrays, then decodes
 * back to text. It supports different OpenAI models (gpt-4, gpt-3.5-turbo, etc.)
 * and provides accurate character position tracking in the original text.
 */
class TokenStrategy implements ChunkerStrategyInterface
{
    use HasOverlap;

    private const DEFAULT_MODEL = 'gpt-4';

    /**
     * Chunk text by token count.
     *
     * @param string $text The text to chunk
     * @param int $size The number of tokens per chunk
     * @param array<string, mixed> $options Options including 'model' (default: gpt-4)
     *
     * @return array<int, Chunk> Array of Chunk value objects
     *
     * @throws ChunkerException When text is empty or model is unsupported
     */
    public function chunk(string $text, int $size, array $options): array
    {
        if ($text === '') {
            throw new ChunkerException('Text cannot be empty');
        }

        $model = $options['model'] ?? config('text-chunker.strategies.token.model', self::DEFAULT_MODEL);

        try {
            $encoder = (new EncoderProvider())->getForModel($model);
        } catch (Exception $e) {
            throw new ChunkerException("Unsupported model: {$model}. Error: {$e->getMessage()}");
        }

        // Encode entire text to tokens
        $tokens = $encoder->encode($text);
        $totalTokens = count($tokens);

        if ($totalTokens <= $size) {
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

        while ($position < $totalTokens) {
            // Extract token slice
            $tokenSlice = array_slice($tokens, $position, $size);

            // Decode tokens back to text
            $chunkText = $encoder->decode($tokenSlice);

            // Calculate character positions in original text
            // We need to find where this chunk appears in the original text
            $startPosition = $this->calculateCharacterPosition($encoder, $tokens, $position);
            $endPosition = $this->calculateCharacterPosition($encoder, $tokens, $position + count($tokenSlice));

            $chunks[] = new Chunk(
                text: $chunkText,
                index: $index,
                start_position: $startPosition,
                end_position: $endPosition
            );

            $index++;

            // Break if we've covered all tokens
            if ($position + count($tokenSlice) >= $totalTokens) {
                break;
            }

            $position += $step;
        }

        return $chunks;
    }

    /**
     * Calculate character position in original text for a given token position.
     *
     * @param \Yethee\Tiktoken\Encoder $encoder The encoder instance
     * @param array<int, int> $tokens All tokens from the original text
     * @param int $tokenPosition The token position to find character position for
     *
     * @return int The character position in the original text
     */
    private function calculateCharacterPosition($encoder, array $tokens, int $tokenPosition): int
    {
        if ($tokenPosition >= count($tokens)) {
            // Decode all tokens to get full text length
            return mb_strlen($encoder->decode($tokens));
        }

        if ($tokenPosition === 0) {
            return 0;
        }

        // Decode tokens up to this position to get character count
        $tokensUpToPosition = array_slice($tokens, 0, $tokenPosition);
        $textUpToPosition = $encoder->decode($tokensUpToPosition);

        return mb_strlen($textUpToPosition);
    }
}
