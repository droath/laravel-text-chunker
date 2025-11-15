<?php

declare(strict_types=1);

namespace Droath\TextChunker\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Droath\TextChunker\TextChunkerManager strategy(string $name, array $options = [])
 * @method static \Droath\TextChunker\TextChunkerManager size(int $size)
 * @method static \Droath\TextChunker\TextChunkerManager overlap(int $percentage)
 * @method static array<\Droath\TextChunker\DataObjects\Chunk> chunk(string $text)
 * @method static void extend(string $name, string $class)
 *
 * @see \Droath\TextChunker\TextChunkerManager
 */
final class TextChunker extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'text-chunker';
    }
}
