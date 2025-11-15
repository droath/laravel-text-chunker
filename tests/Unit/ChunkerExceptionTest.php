<?php

declare(strict_types=1);

use Droath\TextChunker\Exceptions\ChunkerException;

describe('ChunkerException', function () {
    test('can be instantiated with descriptive message', function () {
        $exception = new ChunkerException('Size must be greater than zero');

        expect($exception)->toBeInstanceOf(Exception::class);
        expect($exception->getMessage())->toBe('Size must be greater than zero');
    });

    test('supports different error scenarios with descriptive messages', function () {
        $exceptions = [
            new ChunkerException('Size parameter is required'),
            new ChunkerException('Overlap percentage must be between 0 and 100'),
            new ChunkerException('Text parameter cannot be empty'),
            new ChunkerException('Strategy "invalid" not found. Available strategies: character, token'),
        ];

        expect($exceptions[0]->getMessage())->toContain('Size parameter');
        expect($exceptions[1]->getMessage())->toContain('Overlap');
        expect($exceptions[2]->getMessage())->toContain('Text');
        expect($exceptions[3]->getMessage())->toContain('Strategy');
    });
});
